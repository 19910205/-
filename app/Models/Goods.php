<?php

namespace App\Models;


use App\Events\GoodsDeleted;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goods extends BaseModel
{

    use SoftDeletes;

    protected $table = 'goods';

    protected $dispatchesEvents = [
        'deleted' => GoodsDeleted::class
    ];

    /**
     * 关联分类
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function group()
    {
        return $this->belongsTo(GoodsGroup::class, 'group_id');
    }

    /**
     * 关联优惠券
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function coupon()
    {
        return $this->belongsToMany(Coupon::class, 'coupons_goods', 'goods_id', 'coupons_id');
    }

    /**
     * 关联卡密
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function carmis()
    {
        return $this->hasMany(Carmis::class, 'goods_id');
    }

    /**
     * 关联商品规格
     */
    public function skus()
    {
        return $this->hasMany(GoodsSku::class, 'goods_id');
    }

    /**
     * 关联商品属性
     */
    public function attributes()
    {
        return $this->hasMany(GoodsAttribute::class, 'goods_id');
    }

    /**
     * 关联购物车
     */
    public function cartItems()
    {
        return $this->hasMany(ShoppingCart::class, 'goods_id');
    }

    /**
     * 库存读取器,将自动发货的库存更改为未出售卡密的数量
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function getInStockAttribute()
    {
        if (isset($this->attributes['carmis_count'])
            &&
            $this->attributes['type'] == self::AUTOMATIC_DELIVERY
        ) {
           $this->attributes['in_stock'] = $this->attributes['carmis_count'];
        }
        return $this->attributes['in_stock'];
    }

    /**
     * 是否有多规格
     */
    public function hasSkus(): bool
    {
        return $this->has_sku == 1;
    }

    /**
     * 获取启用的规格
     */
    public function getEnabledSkus()
    {
        return $this->skus()->enabled()->orderBy('sort', 'desc')->get();
    }

    /**
     * 获取可用的规格
     */
    public function getAvailableSkus()
    {
        return $this->skus()->available()->orderBy('sort', 'desc')->get();
    }

    /**
     * 获取价格范围
     */
    public function getPriceRange(): array
    {
        if (!$this->hasSkus()) {
            return [
                'min' => $this->actual_price,
                'max' => $this->actual_price
            ];
        }

        $skus = $this->getEnabledSkus();
        if ($skus->isEmpty()) {
            return [
                'min' => $this->actual_price,
                'max' => $this->actual_price
            ];
        }

        return [
            'min' => $skus->min('price'),
            'max' => $skus->max('price')
        ];
    }

    /**
     * 更新价格范围
     */
    public function updatePriceRange(): bool
    {
        $range = $this->getPriceRange();
        return $this->update([
            'min_price' => $range['min'],
            'max_price' => $range['max']
        ]);
    }

    /**
     * 获取总库存
     */
    public function getTotalStock(): int
    {
        if (!$this->hasSkus()) {
            return $this->in_stock;
        }

        return $this->skus()->sum('stock');
    }

    /**
     * 获取总销量
     */
    public function getTotalSales(): int
    {
        if (!$this->hasSkus()) {
            return $this->sales_volume ?? 0;
        }

        return $this->skus()->sum('sold_count');
    }

    /**
     * 获取组建映射
     *
     * @return array
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public static function getGoodsTypeMap()
    {
        return [
            self::AUTOMATIC_DELIVERY => admin_trans('goods.fields.automatic_delivery'),
            self::MANUAL_PROCESSING => admin_trans('goods.fields.manual_processing')
        ];
    }

}
