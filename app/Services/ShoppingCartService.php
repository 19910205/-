<?php

namespace App\Services;

use App\Models\ShoppingCart;
use App\Models\Order;
use App\Models\Coupon;
use App\Models\Carmis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 购物车服务类
 * 处理购物车相关的业务逻辑
 * 
 * @author Augment Agent
 */
class ShoppingCartService
{
    protected SubsiteService $subsiteService;

    public function __construct(SubsiteService $subsiteService)
    {
        $this->subsiteService = $subsiteService;
    }

    /**
     * 应用优惠券
     */
    public function applyCoupon(ShoppingCart $cartItem, string $couponCode): array
    {
        $coupon = Coupon::where('coupon', $couponCode)
                       ->where('is_open', Coupon::STATUS_OPEN)
                       ->where('is_use', Coupon::STATUS_UNUSED)
                       ->where('ret', '>', 0)
                       ->first();

        if (!$coupon) {
            return [
                'success' => false,
                'message' => '优惠券不存在或已失效'
            ];
        }

        // 检查优惠券是否适用于该商品
        if (!$coupon->goods->contains($cartItem->goods_id)) {
            return [
                'success' => false,
                'message' => '优惠券不适用于该商品'
            ];
        }

        // 检查优惠券使用条件
        if ($coupon->min_amount && $cartItem->total_price < $coupon->min_amount) {
            return [
                'success' => false,
                'message' => '订单金额不满足优惠券使用条件，最低需要 ¥' . $coupon->min_amount
            ];
        }

        $discountAmount = min($coupon->discount, $cartItem->price);
        
        $cartItem->update([
            'coupon_code' => $couponCode,
            'discount_amount' => $discountAmount,
            'total_price' => ($cartItem->price - $discountAmount) * $cartItem->quantity
        ]);

        return [
            'success' => true,
            'message' => '优惠券应用成功',
            'discount' => $discountAmount
        ];
    }

    /**
     * 批量结算
     */
    public function checkout(array $cartItemIds, string $email, ?string $contact, string $sessionId): array
    {
        return DB::transaction(function () use ($cartItemIds, $email, $contact, $sessionId) {
            $cartItems = ShoppingCart::whereIn('id', $cartItemIds)
                ->where('session_id', $sessionId)
                ->with(['goods', 'goodsSku', 'coupon'])
                ->get();

            if ($cartItems->isEmpty()) {
                return [
                    'success' => false,
                    'message' => '购物车为空'
                ];
            }

            $orders = [];
            $errors = [];

            foreach ($cartItems as $cartItem) {
                try {
                    // 检查商品状态和库存
                    $checkResult = $this->checkCartItemAvailability($cartItem);
                    if (!$checkResult['available']) {
                        $errors[] = $checkResult['message'];
                        continue;
                    }

                    // 创建订单
                    $order = $this->createOrderFromCartItem($cartItem, $email, $contact);
                    
                    if ($order) {
                        $orders[] = $order;
                        
                        // 减少库存
                        $this->decreaseStock($cartItem);
                        
                        // 使用优惠券
                        if ($cartItem->coupon_code) {
                            $this->useCoupon($cartItem->coupon_code);
                        }
                        
                        // 创建分站订单记录
                        $this->subsiteService->createSubsiteOrder($order);
                        
                        // 删除购物车项
                        $cartItem->delete();
                    }
                } catch (\Exception $e) {
                    $errors[] = '商品 "' . $cartItem->goods->gd_name . '" 结算失败：' . $e->getMessage();
                }
            }

            if (empty($orders)) {
                return [
                    'success' => false,
                    'message' => '所有商品结算失败：' . implode('; ', $errors)
                ];
            }

            return [
                'success' => true,
                'orders' => $orders,
                'errors' => $errors
            ];
        });
    }

    /**
     * 检查购物车项可用性
     */
    protected function checkCartItemAvailability(ShoppingCart $cartItem): array
    {
        $goods = $cartItem->goods;
        
        // 检查商品状态
        if ($goods->status !== $goods::STATUS_OPEN) {
            return [
                'available' => false,
                'message' => '商品 "' . $goods->gd_name . '" 已下架'
            ];
        }

        // 检查库存
        if ($cartItem->goods_sku_id) {
            $sku = $cartItem->goodsSku;
            if (!$sku || !$sku->isAvailable()) {
                return [
                    'available' => false,
                    'message' => '商品规格 "' . ($sku->name ?? '未知') . '" 不可用'
                ];
            }
            
            if ($sku->available_stock < $cartItem->quantity) {
                return [
                    'available' => false,
                    'message' => '商品规格 "' . $sku->name . '" 库存不足'
                ];
            }
        } else {
            if ($goods->in_stock < $cartItem->quantity) {
                return [
                    'available' => false,
                    'message' => '商品 "' . $goods->gd_name . '" 库存不足'
                ];
            }
        }

        return ['available' => true];
    }

    /**
     * 从购物车项创建订单
     */
    protected function createOrderFromCartItem(ShoppingCart $cartItem, string $email, ?string $contact): Order
    {
        $goods = $cartItem->goods;
        $sku = $cartItem->goodsSku;
        
        $orderData = [
            'order_sn' => $this->generateOrderSn(),
            'goods_id' => $cartItem->goods_id,
            'goods_sku_id' => $cartItem->goods_sku_id,
            'email' => $email,
            'contact' => $contact ?? '',
            'buy_amount' => $cartItem->quantity,
            'actual_price' => $cartItem->price - $cartItem->discount_amount,
            'total_price' => $cartItem->total_price,
            'coupon_discount_price' => $cartItem->discount_amount * $cartItem->quantity,
            'status' => Order::STATUS_PENDING,
            'info' => json_encode($cartItem->goods_snapshot),
            'sku_snapshot' => $cartItem->sku_snapshot ? json_encode($cartItem->sku_snapshot) : null
        ];

        // 如果有优惠券，记录优惠券信息
        if ($cartItem->coupon_code) {
            $orderData['coupon'] = $cartItem->coupon_code;
        }

        return Order::create($orderData);
    }

    /**
     * 减少库存
     */
    protected function decreaseStock(ShoppingCart $cartItem): void
    {
        if ($cartItem->goods_sku_id) {
            // 减少SKU库存
            $cartItem->goodsSku->decreaseStock($cartItem->quantity);
        } else {
            // 减少商品库存
            $goods = $cartItem->goods;
            $goods->decrement('in_stock', $cartItem->quantity);
            $goods->increment('sales_volume', $cartItem->quantity);
        }
    }

    /**
     * 使用优惠券
     */
    protected function useCoupon(string $couponCode): void
    {
        $coupon = Coupon::where('coupon', $couponCode)->first();
        if ($coupon) {
            $coupon->decrement('ret');
            if ($coupon->ret <= 0) {
                $coupon->update(['is_use' => Coupon::STATUS_USED]);
            }
        }
    }

    /**
     * 生成订单号
     */
    protected function generateOrderSn(): string
    {
        do {
            $orderSn = date('YmdHis') . mt_rand(100000, 999999);
        } while (Order::where('order_sn', $orderSn)->exists());

        return $orderSn;
    }

    /**
     * 清理过期购物车
     */
    public function clearExpiredCarts(): int
    {
        return ShoppingCart::expired()->delete();
    }

    /**
     * 获取推荐商品
     */
    public function getRecommendedGoods(ShoppingCart $cartItem, int $limit = 5): array
    {
        $goods = $cartItem->goods;
        
        // 基于商品分组推荐
        $recommendedGoods = $goods->goodsGroup->goods()
            ->where('id', '!=', $goods->id)
            ->where('status', $goods::STATUS_OPEN)
            ->where('in_stock', '>', 0)
            ->orderBy('sales_volume', 'desc')
            ->limit($limit)
            ->get();

        return $recommendedGoods->toArray();
    }

    /**
     * 计算购物车优惠
     */
    public function calculateCartDiscount(array $cartItems): array
    {
        $totalDiscount = 0;
        $appliedCoupons = [];

        foreach ($cartItems as $cartItem) {
            if ($cartItem->discount_amount > 0) {
                $totalDiscount += $cartItem->discount_amount * $cartItem->quantity;
                if ($cartItem->coupon_code && !in_array($cartItem->coupon_code, $appliedCoupons)) {
                    $appliedCoupons[] = $cartItem->coupon_code;
                }
            }
        }

        return [
            'total_discount' => $totalDiscount,
            'applied_coupons' => $appliedCoupons,
            'coupon_count' => count($appliedCoupons)
        ];
    }

    /**
     * 验证购物车完整性
     */
    public function validateCart(string $sessionId, ?string $email = null): array
    {
        $cartItems = ShoppingCart::bySession($sessionId)
            ->notExpired()
            ->with(['goods', 'goodsSku'])
            ->get();

        $validItems = [];
        $invalidItems = [];

        foreach ($cartItems as $cartItem) {
            $checkResult = $this->checkCartItemAvailability($cartItem);
            if ($checkResult['available']) {
                $validItems[] = $cartItem;
            } else {
                $invalidItems[] = [
                    'item' => $cartItem,
                    'reason' => $checkResult['message']
                ];
            }
        }

        return [
            'valid_items' => $validItems,
            'invalid_items' => $invalidItems,
            'total_valid' => count($validItems),
            'total_invalid' => count($invalidItems)
        ];
    }
}
