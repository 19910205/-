<?php

namespace App\Admin\Controllers;

use App\Admin\Repositories\GoodsSku;
use App\Models\GoodsSku as GoodsSkuModel;
use App\Models\Goods;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;

/**
 * 商品规格管理控制器
 * 用于后台管理商品多规格功能
 * 
 * @author Augment Agent
 */
class GoodsSkuController extends AdminController
{
    /**
     * 列表页面
     */
    protected function grid()
    {
        return Grid::make(new GoodsSku(), function (Grid $grid) {
            $grid->column('id', 'ID')->sortable();
            $grid->column('goods.gd_name', '商品名称');
            $grid->column('sku_code', 'SKU编码');
            $grid->column('name', '规格名称');
            $grid->column('attributes', '规格属性')->display(function ($attributes) {
                if (!$attributes) return '-';
                $attrs = [];
                foreach ($attributes as $key => $value) {
                    $attrs[] = $key . ': ' . $value;
                }
                return implode(', ', $attrs);
            });
            $grid->column('price', '价格')->display(function ($price) {
                return '¥' . number_format($price, 2);
            });
            $grid->column('stock', '库存');
            $grid->column('sold_count', '销量');
            $grid->column('status', '状态')->using([
                GoodsSkuModel::STATUS_DISABLED => '禁用',
                GoodsSkuModel::STATUS_ENABLED => '启用'
            ])->label([
                GoodsSkuModel::STATUS_DISABLED => 'danger',
                GoodsSkuModel::STATUS_ENABLED => 'success'
            ]);
            $grid->column('sort', '排序');
            $grid->column('created_at', '创建时间');

            $grid->filter(function (Grid\Filter $filter) {
                $filter->like('sku_code', 'SKU编码');
                $filter->like('name', '规格名称');
                $filter->equal('goods_id', '商品')->select(function () {
                    return Goods::pluck('gd_name', 'id');
                });
                $filter->equal('status', '状态')->select([
                    GoodsSkuModel::STATUS_DISABLED => '禁用',
                    GoodsSkuModel::STATUS_ENABLED => '启用'
                ]);
            });

            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->append('<a href="' . admin_url('carmis?goods_sku_id=' . $actions->getKey()) . '" class="btn btn-xs btn-outline-primary">管理卡密</a>');
            });
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id)
    {
        return Show::make($id, new GoodsSku(), function (Show $show) {
            $show->field('id', 'ID');
            $show->field('goods.gd_name', '商品名称');
            $show->field('sku_code', 'SKU编码');
            $show->field('name', '规格名称');
            $show->field('attributes', '规格属性')->json();
            $show->field('price', '价格')->as(function ($price) {
                return '¥' . number_format($price, 2);
            });
            $show->field('wholesale_price', '批发价格')->as(function ($price) {
                return $price ? '¥' . number_format($price, 2) : '-';
            });
            $show->field('cost_price', '成本价格')->as(function ($price) {
                return $price ? '¥' . number_format($price, 2) : '-';
            });
            $show->field('stock', '库存');
            $show->field('sold_count', '销量');
            $show->field('warning_stock', '预警库存');
            $show->field('status', '状态')->using([
                GoodsSkuModel::STATUS_DISABLED => '禁用',
                GoodsSkuModel::STATUS_ENABLED => '启用'
            ]);
            $show->field('image', '规格图片')->image();
            $show->field('weight', '重量(kg)');
            $show->field('barcode', '条形码');
            $show->field('supplier_code', '供应商编码');
            $show->field('sort', '排序');
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');
        });
    }

    /**
     * 表单页面
     */
    protected function form()
    {
        return Form::make(new GoodsSku(), function (Form $form) {
            $form->display('id', 'ID');
            
            $form->select('goods_id', '商品')
                ->options(function () {
                    return Goods::pluck('gd_name', 'id');
                })
                ->required()
                ->help('选择要添加规格的商品');

            $form->text('sku_code', 'SKU编码')
                ->required()
                ->help('唯一的SKU编码，留空将自动生成');

            $form->text('name', '规格名称')->required();

            $form->keyValue('attributes', '规格属性')
                ->help('设置规格的属性，如颜色、尺寸等');

            $form->currency('price', '价格')->symbol('¥')->required();
            $form->currency('wholesale_price', '批发价格')->symbol('¥');
            $form->currency('cost_price', '成本价格')->symbol('¥');

            $form->number('stock', '库存')->default(0)->min(0);
            $form->number('warning_stock', '预警库存')->default(10)->min(0);

            $form->radio('status', '状态')->options([
                GoodsSkuModel::STATUS_DISABLED => '禁用',
                GoodsSkuModel::STATUS_ENABLED => '启用'
            ])->default(GoodsSkuModel::STATUS_ENABLED)->required();

            $form->image('image', '规格图片')->uniqueName();
            $form->decimal('weight', '重量(kg)')->help('商品重量，单位：千克');
            $form->text('barcode', '条形码');
            $form->text('supplier_code', '供应商编码');
            $form->number('sort', '排序')->default(0)->help('数值越大排序越靠前');

            $form->display('sold_count', '销量');
            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');

            // 保存前处理
            $form->saving(function (Form $form) {
                // 如果没有填写SKU编码，自动生成
                if (empty($form->sku_code)) {
                    $form->sku_code = GoodsSkuModel::generateSkuCode($form->goods_id);
                }
            });

            // 保存后处理
            $form->saved(function (Form $form) {
                // 更新商品的价格范围
                $goods = Goods::find($form->model()->goods_id);
                if ($goods) {
                    $goods->update(['has_sku' => 1]);
                    $goods->updatePriceRange();
                }
            });
        });
    }
}
