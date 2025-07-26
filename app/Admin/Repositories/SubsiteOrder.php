<?php

namespace App\Admin\Repositories;

use App\Models\SubsiteOrder as SubsiteOrderModel;
use Dcat\Admin\Repositories\EloquentRepository;

/**
 * 分站订单数据仓库
 * 
 * @author Augment Agent
 */
class SubsiteOrder extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = SubsiteOrderModel::class;

    protected $subsiteId;

    public function __construct($subsiteId = null)
    {
        $this->subsiteId = $subsiteId;
        parent::__construct();
    }

    public function with($relations)
    {
        $this->relations = array_merge($this->relations, (array) $relations);
        return $this;
    }
}
