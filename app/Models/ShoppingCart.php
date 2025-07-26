<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * 购物车模型
 * 用于管理用户购物车商品
 * 
 * @author Augment Agent
 */
class ShoppingCart extends BaseModel
{
    use HasFactory;

    protected $table = 'shopping_carts';

    protected $fillable = [
        'session_id',
        'user_email',
        'goods_id',
        'goods_sku_id',
        'quantity',
        'price',
        'total_price',
        'goods_snapshot',
        'sku_snapshot',
        'custom_fields',
        'coupon_code',
        'discount_amount',
        'expires_at'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'goods_snapshot' => 'array',
        'sku_snapshot' => 'array',
        'custom_fields' => 'array',
        'expires_at' => 'datetime'
    ];

    /**
     * 关联商品
     */
    public function goods(): BelongsTo
    {
        return $this->belongsTo(Goods::class);
    }

    /**
     * 关联商品规格
     */
    public function goodsSku(): BelongsTo
    {
        return $this->belongsTo(GoodsSku::class);
    }

    /**
     * 关联优惠券
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_code', 'coupon');
    }

    /**
     * 按会话ID查询
     */
    public function scopeBySession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * 按邮箱查询
     */
    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('user_email', $email);
    }

    /**
     * 未过期的购物车
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * 已过期的购物车
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * 更新数量
     */
    public function updateQuantity(int $quantity): bool
    {
        $this->quantity = $quantity;
        $this->total_price = ($this->price - $this->discount_amount) * $quantity;
        return $this->save();
    }

    /**
     * 增加数量
     */
    public function incrementQuantity(int $amount = 1): bool
    {
        return $this->updateQuantity($this->quantity + $amount);
    }

    /**
     * 减少数量
     */
    public function decrementQuantity(int $amount = 1): bool
    {
        $newQuantity = max(1, $this->quantity - $amount);
        return $this->updateQuantity($newQuantity);
    }

    /**
     * 是否过期
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * 延长过期时间
     */
    public function extend(int $hours = 24): bool
    {
        return $this->update([
            'expires_at' => now()->addHours($hours)
        ]);
    }

    /**
     * 应用优惠券
     */
    public function applyCoupon(string $couponCode): bool
    {
        $coupon = Coupon::where('coupon', $couponCode)
                       ->where('is_open', Coupon::STATUS_OPEN)
                       ->where('is_use', Coupon::STATUS_UNUSED)
                       ->where('ret', '>', 0)
                       ->first();

        if (!$coupon) {
            return false;
        }

        // 检查优惠券是否适用于该商品
        if (!$coupon->goods->contains($this->goods_id)) {
            return false;
        }

        $discountAmount = min($coupon->discount, $this->price);
        
        return $this->update([
            'coupon_code' => $couponCode,
            'discount_amount' => $discountAmount,
            'total_price' => ($this->price - $discountAmount) * $this->quantity
        ]);
    }

    /**
     * 移除优惠券
     */
    public function removeCoupon(): bool
    {
        return $this->update([
            'coupon_code' => null,
            'discount_amount' => 0,
            'total_price' => $this->price * $this->quantity
        ]);
    }

    /**
     * 添加到购物车
     */
    public static function addToCart(array $data): self
    {
        $cart = self::where('session_id', $data['session_id'])
                   ->where('goods_id', $data['goods_id'])
                   ->where('goods_sku_id', $data['goods_sku_id'] ?? null)
                   ->first();

        if ($cart) {
            $cart->incrementQuantity($data['quantity'] ?? 1);
            return $cart;
        }

        $data['total_price'] = $data['price'] * ($data['quantity'] ?? 1);
        $data['expires_at'] = now()->addHours(24);

        return self::create($data);
    }

    /**
     * 获取购物车统计
     */
    public static function getCartTotal(string $sessionId, ?string $email = null): array
    {
        $query = self::bySession($sessionId)->notExpired();
        
        if ($email) {
            $query->where(function ($q) use ($email) {
                $q->where('user_email', $email)
                  ->orWhereNull('user_email');
            });
        }

        $items = $query->get();
        
        return [
            'items' => $items,
            'total_quantity' => $items->sum('quantity'),
            'total_price' => $items->sum('total_price'),
            'original_price' => $items->sum(function($item) {
                return $item->price * $item->quantity;
            }),
            'total_discount' => $items->sum(function($item) {
                return $item->discount_amount * $item->quantity;
            }),
            'item_count' => $items->count()
        ];
    }

    /**
     * 清理过期购物车
     */
    public static function clearExpired(): int
    {
        return self::expired()->delete();
    }

    /**
     * 清空购物车
     */
    public static function clearCart(string $sessionId, ?string $email = null): int
    {
        $query = self::bySession($sessionId);
        
        if ($email) {
            $query->where('user_email', $email);
        }

        return $query->delete();
    }

    /**
     * 获取商品快照
     */
    public function getGoodsSnapshot(string $key = null)
    {
        if ($key) {
            return $this->goods_snapshot[$key] ?? null;
        }
        return $this->goods_snapshot;
    }

    /**
     * 获取SKU快照
     */
    public function getSkuSnapshot(string $key = null)
    {
        if ($key) {
            return $this->sku_snapshot[$key] ?? null;
        }
        return $this->sku_snapshot;
    }

    /**
     * 获取自定义字段
     */
    public function getCustomField(string $key = null)
    {
        if ($key) {
            return $this->custom_fields[$key] ?? null;
        }
        return $this->custom_fields;
    }

    /**
     * 设置自定义字段
     */
    public function setCustomField(string $key, $value): bool
    {
        $fields = $this->custom_fields ?? [];
        $fields[$key] = $value;
        return $this->update(['custom_fields' => $fields]);
    }
}
