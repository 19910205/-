<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * 商品规格模型
 * 用于管理商品的多规格信息
 * 
 * @author Augment Agent
 */
class GoodsSku extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'goods_skus';

    // 状态常量
    const STATUS_DISABLED = 0;  // 禁用
    const STATUS_ENABLED = 1;   // 启用

    protected $fillable = [
        'goods_id',
        'sku_code',
        'name',
        'attributes',
        'price',
        'wholesale_price',
        'cost_price',
        'stock',
        'sold_count',
        'warning_stock',
        'status',
        'image',
        'weight',
        'barcode',
        'supplier_code',
        'sort',
        'extra_data'
    ];

    protected $casts = [
        'attributes' => 'array',
        'price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'extra_data' => 'array'
    ];

    /**
     * 获取状态映射
     */
    public static function getStatusMap(): array
    {
        return [
            self::STATUS_DISABLED => '禁用',
            self::STATUS_ENABLED => '启用'
        ];
    }

    /**
     * 关联商品
     */
    public function goods(): BelongsTo
    {
        return $this->belongsTo(Goods::class);
    }

    /**
     * 关联卡密
     */
    public function carmis(): HasMany
    {
        return $this->hasMany(Carmis::class, 'goods_sku_id');
    }

    /**
     * 关联购物车
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(ShoppingCart::class, 'goods_sku_id');
    }

    /**
     * 关联订单
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'goods_sku_id');
    }

    /**
     * 启用的规格
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 有库存的规格
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * 可用的规格
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->enabled()->inStock();
    }

    /**
     * 库存预警
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw('stock <= warning_stock');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatusMap()[$this->status] ?? '';
    }

    /**
     * 获取可用库存
     */
    public function getAvailableStockAttribute(): int
    {
        if ($this->goods && $this->goods->type === Goods::AUTOMATIC_DELIVERY) {
            return $this->carmis()->where('status', Carmis::STATUS_UNSOLD)->count();
        }
        return $this->stock;
    }

    /**
     * 是否启用
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 是否有库存
     */
    public function isInStock(): bool
    {
        return $this->available_stock > 0;
    }

    /**
     * 是否可用
     */
    public function isAvailable(): bool
    {
        return $this->isEnabled() && $this->isInStock();
    }

    /**
     * 是否库存预警
     */
    public function isLowStock(): bool
    {
        return $this->stock <= $this->warning_stock;
    }

    /**
     * 减少库存
     */
    public function decreaseStock(int $quantity): bool
    {
        if ($this->stock < $quantity) {
            return false;
        }

        return $this->update([
            'stock' => $this->stock - $quantity,
            'sold_count' => $this->sold_count + $quantity
        ]);
    }

    /**
     * 增加库存
     */
    public function increaseStock(int $quantity): bool
    {
        return $this->update([
            'stock' => $this->stock + $quantity
        ]);
    }

    /**
     * 获取属性值
     */
    public function getAttributeValue(string $attributeName): ?string
    {
        return $this->attributes[$attributeName] ?? null;
    }

    /**
     * 是否有属性
     */
    public function hasAttribute(string $attributeName): bool
    {
        return isset($this->attributes[$attributeName]);
    }

    /**
     * 获取格式化属性
     */
    public function getFormattedAttributes(): array
    {
        $formatted = [];
        foreach ($this->attributes as $key => $value) {
            $formatted[] = [
                'name' => $key,
                'value' => $value
            ];
        }
        return $formatted;
    }

    /**
     * 生成SKU编码
     */
    public static function generateSkuCode(int $goodsId): string
    {
        $prefix = 'SKU' . str_pad($goodsId, 6, '0', STR_PAD_LEFT);
        $suffix = strtoupper(substr(md5(uniqid()), 0, 6));
        return $prefix . $suffix;
    }

    /**
     * 获取批发价格
     */
    public function getWholesalePrice(int $quantity): float
    {
        if (!$this->wholesale_price || $quantity < 2) {
            return $this->price;
        }

        // 根据数量阶梯定价
        if ($quantity >= 100) {
            return $this->wholesale_price * 0.8;
        } elseif ($quantity >= 50) {
            return $this->wholesale_price * 0.9;
        } elseif ($quantity >= 10) {
            return $this->wholesale_price;
        }

        return $this->price;
    }

    /**
     * 获取实际价格
     */
    public function getActualPrice(int $quantity = 1): float
    {
        return $this->getWholesalePrice($quantity);
    }

    /**
     * 获取扩展数据
     */
    public function getExtraData(string $key = null)
    {
        if ($key) {
            return $this->extra_data[$key] ?? null;
        }
        return $this->extra_data;
    }

    /**
     * 设置扩展数据
     */
    public function setExtraData(string $key, $value): bool
    {
        $data = $this->extra_data ?? [];
        $data[$key] = $value;
        return $this->update(['extra_data' => $data]);
    }

    /**
     * 获取利润率
     */
    public function getProfitMarginAttribute(): float
    {
        if (!$this->cost_price || $this->cost_price <= 0) {
            return 0;
        }
        return (($this->price - $this->cost_price) / $this->cost_price) * 100;
    }

    /**
     * 获取利润
     */
    public function getProfitAttribute(): float
    {
        return $this->price - ($this->cost_price ?? 0);
    }

    /**
     * 获取带商品名称的规格名称
     */
    public function getNameWithGoodsAttribute(): string
    {
        return $this->goods->gd_name . ' - ' . $this->name;
    }
}
