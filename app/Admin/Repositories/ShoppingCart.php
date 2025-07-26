<?php

namespace App\Admin\Repositories;

use App\Models\ShoppingCart as ShoppingCartModel;
use Dcat\Admin\Repositories\EloquentRepository;

/**
 * 购物车数据仓库
 * 
 * @author Augment Agent
 */
class ShoppingCart extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = ShoppingCartModel::class;
}
