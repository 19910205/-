<?php

namespace App\Admin\Controllers;

use App\Admin\Repositories\Subsite;
use App\Models\Subsite as SubsiteModel;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;

/**
 * 分站管理控制器
 * 用于后台管理分站功能
 * 
 * @author Augment Agent
 */
class SubsiteController extends AdminController
{
    /**
     * 列表页面
     */
    protected function grid()
    {
        return Grid::make(new Subsite(), function (Grid $grid) {
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '分站名称');
            $grid->column('domain', '分站域名');
            $grid->column('type', '分站类型')->using([
                SubsiteModel::TYPE_LOCAL => '本站分站',
                SubsiteModel::TYPE_THIRD_PARTY => '第三方对接'
            ])->label([
                SubsiteModel::TYPE_LOCAL => 'primary',
                SubsiteModel::TYPE_THIRD_PARTY => 'success'
            ]);
            $grid->column('status', '状态')->using([
                SubsiteModel::STATUS_DISABLED => '禁用',
                SubsiteModel::STATUS_ENABLED => '启用'
            ])->label([
                SubsiteModel::STATUS_DISABLED => 'danger',
                SubsiteModel::STATUS_ENABLED => 'success'
            ]);
            $grid->column('commission_rate', '佣金比例(%)');
            $grid->column('balance', '余额')->display(function ($balance) {
                return '¥' . number_format($balance, 2);
            });
            $grid->column('last_sync_at', '最后同步时间');
            $grid->column('created_at', '创建时间');

            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('name', '分站名称');
                $filter->equal('type', '分站类型')->select([
                    SubsiteModel::TYPE_LOCAL => '本站分站',
                    SubsiteModel::TYPE_THIRD_PARTY => '第三方对接'
                ]);
                $filter->equal('status', '状态')->select([
                    SubsiteModel::STATUS_DISABLED => '禁用',
                    SubsiteModel::STATUS_ENABLED => '启用'
                ]);
            });

            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->append('<a href="' . admin_url('subsite/' . $actions->getKey() . '/orders') . '" class="btn btn-xs btn-outline-primary">订单管理</a>');
                $actions->append('<a href="' . admin_url('subsite/' . $actions->getKey() . '/statistics') . '" class="btn btn-xs btn-outline-info">统计数据</a>');
            });
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id)
    {
        return Show::make($id, new Subsite(), function (Show $show) {
            $show->field('id', 'ID');
            $show->field('name', '分站名称');
            $show->field('domain', '分站域名');
            $show->field('subdomain', '子域名');
            $show->field('type', '分站类型')->using([
                SubsiteModel::TYPE_LOCAL => '本站分站',
                SubsiteModel::TYPE_THIRD_PARTY => '第三方对接'
            ]);
            $show->field('status', '状态')->using([
                SubsiteModel::STATUS_DISABLED => '禁用',
                SubsiteModel::STATUS_ENABLED => '启用'
            ]);
            $show->field('commission_rate', '佣金比例(%)');
            $show->field('balance', '余额')->as(function ($balance) {
                return '¥' . number_format($balance, 2);
            });
            $show->field('api_url', 'API地址');
            $show->field('api_key', 'API密钥');
            $show->field('contact_email', '联系邮箱');
            $show->field('contact_phone', '联系电话');
            $show->field('description', '分站描述');
            $show->field('last_sync_at', '最后同步时间');
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');
        });
    }

    /**
     * 表单页面
     */
    protected function form()
    {
        return Form::make(new Subsite(), function (Form $form) {
            $form->display('id', 'ID');
            $form->text('name', '分站名称')->required();
            $form->text('domain', '分站域名')->required()->help('请输入完整的域名，如：shop.example.com');
            $form->text('subdomain', '子域名')->help('可选，用于生成子域名访问');
            
            $form->radio('type', '分站类型')->options([
                SubsiteModel::TYPE_LOCAL => '本站分站',
                SubsiteModel::TYPE_THIRD_PARTY => '第三方对接'
            ])->default(SubsiteModel::TYPE_LOCAL)->required();

            $form->radio('status', '状态')->options([
                SubsiteModel::STATUS_DISABLED => '禁用',
                SubsiteModel::STATUS_ENABLED => '启用'
            ])->default(SubsiteModel::STATUS_ENABLED)->required();

            $form->decimal('commission_rate', '佣金比例(%)')
                ->default(0)
                ->min(0)
                ->max(100)
                ->help('设置分站的佣金比例，0-100之间');

            $form->divider('API配置');
            $form->url('api_url', 'API地址')->help('第三方对接时需要填写');
            $form->text('api_key', 'API密钥')->help('API访问密钥');
            $form->password('api_secret', 'API秘钥')->help('API访问秘钥');

            $form->divider('联系信息');
            $form->email('contact_email', '联系邮箱');
            $form->text('contact_phone', '联系电话');
            $form->textarea('description', '分站描述');

            $form->display('balance', '当前余额')->with(function ($value) {
                return '¥' . number_format($value ?? 0, 2);
            });

            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');
        });
    }

    /**
     * 分站订单管理
     */
    public function orders($id)
    {
        $subsite = SubsiteModel::findOrFail($id);
        
        return Grid::make(new \App\Admin\Repositories\SubsiteOrder($id), function (Grid $grid) use ($subsite) {
            $grid->model()->where('subsite_id', $subsite->id);
            
            $grid->column('id', 'ID')->sortable();
            $grid->column('order.order_sn', '订单号');
            $grid->column('order.goods.gd_name', '商品名称');
            $grid->column('commission_amount', '佣金金额')->display(function ($amount) {
                return '¥' . number_format($amount, 2);
            });
            $grid->column('commission_status', '佣金状态')->using([
                0 => '未结算',
                1 => '已结算'
            ])->label([
                0 => 'warning',
                1 => 'success'
            ]);
            $grid->column('sync_status', '同步状态')->using([
                0 => '未同步',
                1 => '已同步',
                2 => '同步失败'
            ])->label([
                0 => 'default',
                1 => 'success',
                2 => 'danger'
            ]);
            $grid->column('created_at', '创建时间');

            $grid->filter(function (Grid\Filter $filter) {
                $filter->like('order.order_sn', '订单号');
                $filter->equal('commission_status', '佣金状态')->select([
                    0 => '未结算',
                    1 => '已结算'
                ]);
                $filter->equal('sync_status', '同步状态')->select([
                    0 => '未同步',
                    1 => '已同步',
                    2 => '同步失败'
                ]);
            });

            $grid->header(function () use ($subsite) {
                return '<h4>分站：' . $subsite->name . ' - 订单管理</h4>';
            });
        });
    }

    /**
     * 分站统计
     */
    public function statistics($id)
    {
        $subsite = SubsiteModel::findOrFail($id);
        $subsiteService = app('\App\Services\SubsiteService');
        $stats = $subsiteService->getSubsiteStatistics($subsite);

        return view('admin.subsite.statistics', compact('subsite', 'stats'));
    }
}
