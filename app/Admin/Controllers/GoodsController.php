<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Post\BatchRestore;
use App\Admin\Actions\Post\Restore;
use App\Admin\Repositories\Goods;
use App\Models\Carmis;
use App\Models\Coupon;
use App\Models\GoodsGroup as GoodsGroupModel;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;
use App\Models\Goods as GoodsModel;

class GoodsController extends AdminController
{


    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new Goods(['group', 'coupon']), function (Grid $grid) {
            $grid->model()->orderBy('id', 'DESC');
            $grid->column('id')->sortable();
            $grid->column('picture')->image('', 100, 100);
            $grid->column('gd_name');
            $grid->column('gd_description');
            $grid->column('gd_keywords');
            $grid->column('group.gp_name', admin_trans('goods.fields.group_id'));
            $grid->column('type')
                ->using(GoodsModel::getGoodsTypeMap())
                ->label([
                    GoodsModel::AUTOMATIC_DELIVERY => Admin::color()->success(),
                    GoodsModel::MANUAL_PROCESSING => Admin::color()->info(),
                ]);
            $grid->column('retail_price');
            $grid->column('actual_price')->sortable();
            $grid->column('in_stock')->display(function () {
                // 如果为自动发货，则加载库存卡密
                if ($this->type == GoodsModel::AUTOMATIC_DELIVERY) {
                    if ($this->has_sku) {
                        // 有多规格的商品，统计所有SKU的卡密
                        return Carmis::query()->whereIn('goods_sku_id', $this->skus->pluck('id'))
                            ->where('status', Carmis::STATUS_UNSOLD)
                            ->count();
                    } else {
                        // 没有多规格的商品
                        return Carmis::query()->where('goods_id', $this->id)
                            ->whereNull('goods_sku_id')
                            ->where('status', Carmis::STATUS_UNSOLD)
                            ->count();
                    }
                } else {
                    return $this->has_sku ? $this->skus->sum('stock') : $this->in_stock;
                }
            });
            $grid->column('sales_volume');
            $grid->column('ord')->editable()->sortable();
            $grid->column('is_open')->switch();
            $grid->column('created_at')->sortable();
            $grid->column('updated_at');
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id');
                $filter->like('gd_name');
                $filter->equal('type')->select(GoodsModel::getGoodsTypeMap());
                $filter->equal('group_id')->select(GoodsGroupModel::query()->pluck('gp_name', 'id'));
                $filter->scope(admin_trans('dujiaoka.trashed'))->onlyTrashed();
                $filter->equal('coupon.coupons_id', admin_trans('goods.fields.coupon_id'))->select(
                    Coupon::query()->pluck('coupon', 'id')
                );
            });
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                if (request('_scope_') == admin_trans('dujiaoka.trashed')) {
                    $actions->append(new Restore(GoodsModel::class));
                } else {
                    // 添加规格管理按钮
                    $actions->append('<a href="' . admin_url('goods-sku?goods_id=' . $actions->getKey()) . '" class="btn btn-xs btn-outline-primary">规格管理</a>');
                    // 添加卡密管理按钮
                    $actions->append('<a href="' . admin_url('carmis?goods_id=' . $actions->getKey()) . '" class="btn btn-xs btn-outline-success">卡密管理</a>');
                }
            });
            $grid->batchActions(function (Grid\Tools\BatchActions $batch) {
                if (request('_scope_') == admin_trans('dujiaoka.trashed')) {
                    $batch->add(new BatchRestore(GoodsModel::class));
                }
            });
        });
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        return Show::make($id, new Goods(), function (Show $show) {
            $show->id('id');
            $show->field('gd_name');
            $show->field('gd_description');
            $show->field('gd_keywords');
            $show->field('picture')->image();
            $show->field('retail_price');
            $show->field('actual_price');
            $show->field('in_stock');
            $show->field('ord');
            $show->field('sales_volume');
            $show->field('type')->as(function ($type) {
                if ($type == GoodsModel::AUTOMATIC_DELIVERY) {
                    return admin_trans('goods.fields.automatic_delivery');
                } else {
                    return admin_trans('goods.fields.manual_processing');
                }
            });
            $show->field('is_open')->as(function ($isOpen) {
                if ($isOpen == GoodsGroupModel::STATUS_OPEN) {
                    return admin_trans('dujiaoka.status_open');
                } else {
                    return admin_trans('dujiaoka.status_close');
                }
            });
            $show->wholesale_price_cnf()->unescape()->as(function ($wholesalePriceCnf) {
                return  "<textarea class=\"form-control field_wholesale_price_cnf _normal_\"  rows=\"10\" cols=\"30\">" . $wholesalePriceCnf . "</textarea>";
            });
            $show->other_ipu_cnf()->unescape()->as(function ($otherIpuCnf) {
                return  "<textarea class=\"form-control field_wholesale_price_cnf _normal_\"  rows=\"10\" cols=\"30\">" . $otherIpuCnf . "</textarea>";
            });
            $show->api_hook()->unescape()->as(function ($apiHook) {
                return  "<textarea class=\"form-control field_wholesale_price_cnf _normal_\"  rows=\"10\" cols=\"30\">" . $apiHook . "</textarea>";
            });;
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new Goods(), function (Form $form) {
            $form->display('id');
            $form->text('gd_name')->required();
            $form->text('gd_description')->required();
            $form->text('gd_keywords')->required();
            $form->select('group_id')->options(
                GoodsGroupModel::query()->pluck('gp_name', 'id')
            )->required();
            $form->image('picture')->autoUpload()->uniqueName()->help(admin_trans('goods.helps.picture'));
            $form->radio('type')->options(GoodsModel::getGoodsTypeMap())->default(GoodsModel::AUTOMATIC_DELIVERY)->required();

            // 多规格设置
            $form->switch('has_sku', '启用多规格')->default(0)->help('启用后可以为商品设置多个规格');

            $form->currency('retail_price')->default(0)->help(admin_trans('goods.helps.retail_price'));
            $form->currency('actual_price')->default(0)->required()->help('单规格商品价格，多规格商品此价格作为基础价格');
            $form->number('in_stock')->help('单规格商品库存，多规格商品请在规格管理中设置');
            $form->number('sales_volume');
            $form->number('buy_limit_num')->help(admin_trans('goods.helps.buy_limit_num'));
            $form->editor('buy_prompt');
            $form->editor('description');
            $form->textarea('other_ipu_cnf')->help(admin_trans('goods.helps.other_ipu_cnf'));
            $form->textarea('wholesale_price_cnf')->help(admin_trans('goods.helps.wholesale_price_cnf'));
            $form->textarea('api_hook');
            $form->number('ord')->default(1)->help(admin_trans('dujiaoka.ord'));
            $form->switch('is_open')->default(GoodsModel::STATUS_OPEN);
        });
    }
}
