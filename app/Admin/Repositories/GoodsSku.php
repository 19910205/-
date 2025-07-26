<?php

namespace App\Admin\Repositories;

use App\Models\GoodsSku as GoodsSkuModel;
use Dcat\Admin\Repositories\EloquentRepository;

/**
 * 商品规格数据仓库
 * 
 * @author Augment Agent
 */
class GoodsSku extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = GoodsSkuModel::class;
}
