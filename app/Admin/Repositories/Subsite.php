<?php

namespace App\Admin\Repositories;

use App\Models\Subsite as SubsiteModel;
use Dcat\Admin\Repositories\EloquentRepository;

/**
 * 分站数据仓库
 * 
 * @author Augment Agent
 */
class Subsite extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = SubsiteModel::class;
}
