<?php

namespace App\Http\Controllers;

use App\Models\ShoppingCart;
use App\Models\Goods;
use App\Models\GoodsSku;
use App\Models\Coupon;
use App\Services\ShoppingCartService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * 购物车控制器
 * 用于管理用户购物车功能
 * 
 * @author Augment Agent
 */
class ShoppingCartController extends Controller
{
    protected ShoppingCartService $cartService;

    public function __construct(ShoppingCartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * 购物车页面
     */
    public function index(Request $request)
    {
        $sessionId = $request->session()->getId();
        $userEmail = $request->input('email');
        
        $cartData = ShoppingCart::getCartTotal($sessionId, $userEmail);
        
        return view('cart.index', [
            'cartItems' => $cartData['items'],
            'totalQuantity' => $cartData['total_quantity'],
            'totalPrice' => $cartData['total_price'],
            'originalPrice' => $cartData['original_price'],
            'totalDiscount' => $cartData['total_discount'],
            'itemCount' => $cartData['item_count']
        ]);
    }

    /**
     * 添加商品到购物车
     */
    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goods_id' => 'required|exists:goods,id',
            'goods_sku_id' => 'nullable|exists:goods_skus,id',
            'quantity' => 'required|integer|min:1|max:999',
            'email' => 'nullable|email'
        ]);

        try {
            $goods = Goods::findOrFail($validated['goods_id']);
            
            // 检查商品状态
            if ($goods->status !== Goods::STATUS_OPEN) {
                return response()->json([
                    'code' => 400,
                    'message' => '商品已下架'
                ]);
            }

            // 检查库存
            $sku = null;
            if ($validated['goods_sku_id']) {
                $sku = GoodsSku::findOrFail($validated['goods_sku_id']);
                if (!$sku->isAvailable()) {
                    return response()->json([
                        'code' => 400,
                        'message' => '商品规格不可用'
                    ]);
                }
                
                if ($sku->available_stock < $validated['quantity']) {
                    return response()->json([
                        'code' => 400,
                        'message' => '库存不足，当前库存：' . $sku->available_stock
                    ]);
                }
            } else {
                if ($goods->in_stock < $validated['quantity']) {
                    return response()->json([
                        'code' => 400,
                        'message' => '库存不足，当前库存：' . $goods->in_stock
                    ]);
                }
            }

            $cartData = [
                'session_id' => $request->session()->getId(),
                'user_email' => $validated['email'] ?? null,
                'goods_id' => $validated['goods_id'],
                'goods_sku_id' => $validated['goods_sku_id'] ?? null,
                'quantity' => $validated['quantity'],
                'price' => $sku ? $sku->price : $goods->actual_price,
                'goods_snapshot' => $goods->toArray(),
                'sku_snapshot' => $sku ? $sku->toArray() : null
            ];

            $cartItem = ShoppingCart::addToCart($cartData);
            
            // 获取购物车统计
            $cartTotal = ShoppingCart::getCartTotal($request->session()->getId(), $validated['email'] ?? null);

            return response()->json([
                'code' => 200,
                'message' => '添加成功',
                'data' => [
                    'cart_item' => $cartItem,
                    'cart_total' => $cartTotal
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '添加失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 更新购物车商品数量
     */
    public function update(Request $request, ShoppingCart $cartItem): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:999'
        ]);

        try {
            // 验证会话
            if ($cartItem->session_id !== $request->session()->getId()) {
                return response()->json([
                    'code' => 403,
                    'message' => '无权限操作'
                ]);
            }

            // 检查库存
            $availableStock = $cartItem->goods_sku_id 
                ? $cartItem->goodsSku->available_stock 
                : $cartItem->goods->in_stock;

            if ($availableStock < $validated['quantity']) {
                return response()->json([
                    'code' => 400,
                    'message' => '库存不足，当前库存：' . $availableStock
                ]);
            }

            $cartItem->updateQuantity($validated['quantity']);
            
            // 获取购物车统计
            $cartTotal = ShoppingCart::getCartTotal($request->session()->getId());

            return response()->json([
                'code' => 200,
                'message' => '更新成功',
                'data' => [
                    'cart_item' => $cartItem->fresh(),
                    'cart_total' => $cartTotal
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '更新失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 删除购物车商品
     */
    public function remove(Request $request, ShoppingCart $cartItem): JsonResponse
    {
        try {
            // 验证会话
            if ($cartItem->session_id !== $request->session()->getId()) {
                return response()->json([
                    'code' => 403,
                    'message' => '无权限操作'
                ]);
            }

            $cartItem->delete();
            
            // 获取购物车统计
            $cartTotal = ShoppingCart::getCartTotal($request->session()->getId());

            return response()->json([
                'code' => 200,
                'message' => '删除成功',
                'data' => $cartTotal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '删除失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 清空购物车
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->session()->getId();
            $userEmail = $request->input('email');
            
            ShoppingCart::clearCart($sessionId, $userEmail);

            return response()->json([
                'code' => 200,
                'message' => '购物车已清空'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '清空失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 应用优惠券
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => 'required|string',
            'cart_item_id' => 'required|exists:shopping_carts,id'
        ]);

        try {
            $cartItem = ShoppingCart::findOrFail($validated['cart_item_id']);
            
            // 验证会话
            if ($cartItem->session_id !== $request->session()->getId()) {
                return response()->json([
                    'code' => 403,
                    'message' => '无权限操作'
                ]);
            }

            $result = $this->cartService->applyCoupon($cartItem, $validated['coupon_code']);
            
            if ($result['success']) {
                // 获取购物车统计
                $cartTotal = ShoppingCart::getCartTotal($request->session()->getId());
                
                return response()->json([
                    'code' => 200,
                    'message' => '优惠券应用成功',
                    'data' => [
                        'cart_item' => $cartItem->fresh(),
                        'cart_total' => $cartTotal,
                        'discount' => $result['discount']
                    ]
                ]);
            } else {
                return response()->json([
                    'code' => 400,
                    'message' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '应用失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 移除优惠券
     */
    public function removeCoupon(Request $request, ShoppingCart $cartItem): JsonResponse
    {
        try {
            // 验证会话
            if ($cartItem->session_id !== $request->session()->getId()) {
                return response()->json([
                    'code' => 403,
                    'message' => '无权限操作'
                ]);
            }

            $cartItem->removeCoupon();
            
            // 获取购物车统计
            $cartTotal = ShoppingCart::getCartTotal($request->session()->getId());

            return response()->json([
                'code' => 200,
                'message' => '优惠券已移除',
                'data' => [
                    'cart_item' => $cartItem->fresh(),
                    'cart_total' => $cartTotal
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '移除失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 获取购物车统计
     */
    public function getTotal(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->session()->getId();
            $userEmail = $request->input('email');
            
            $cartTotal = ShoppingCart::getCartTotal($sessionId, $userEmail);

            return response()->json([
                'code' => 200,
                'data' => $cartTotal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '获取失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 批量结算
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cart_item_ids' => 'required|array',
            'cart_item_ids.*' => 'exists:shopping_carts,id',
            'email' => 'required|email',
            'contact' => 'nullable|string'
        ]);

        try {
            $result = $this->cartService->checkout(
                $validated['cart_item_ids'],
                $validated['email'],
                $validated['contact'] ?? null,
                $request->session()->getId()
            );

            if ($result['success']) {
                return response()->json([
                    'code' => 200,
                    'message' => '订单创建成功',
                    'data' => $result['orders']
                ]);
            } else {
                return response()->json([
                    'code' => 400,
                    'message' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '结算失败：' . $e->getMessage()
            ]);
        }
    }
}
