<?php

namespace App\Admin\Controllers;

use App\Admin\Repositories\ShoppingCart;
use App\Models\ShoppingCart as ShoppingCartModel;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;

/**
 * 购物车管理控制器
 * 用于后台查看和管理购物车数据
 * 
 * @author Augment Agent
 */
class ShoppingCartController extends AdminController
{
    /**
     * 列表页面
     */
    protected function grid()
    {
        return Grid::make(new ShoppingCart(), function (Grid $grid) {
            $grid->column('id', 'ID')->sortable();
            $grid->column('session_id', '会话ID')->limit(20);
            $grid->column('user_email', '用户邮箱');
            $grid->column('goods.gd_name', '商品名称');
            $grid->column('goodsSku.name', '规格名称');
            $grid->column('quantity', '数量');
            $grid->column('price', '单价')->display(function ($price) {
                return '¥' . number_format($price, 2);
            });
            $grid->column('total_price', '总价')->display(function ($price) {
                return '¥' . number_format($price, 2);
            });
            $grid->column('discount_amount', '优惠金额')->display(function ($amount) {
                return $amount > 0 ? '¥' . number_format($amount, 2) : '-';
            });
            $grid->column('coupon_code', '优惠券');
            $grid->column('expires_at', '过期时间');
            $grid->column('created_at', '创建时间');

            $grid->filter(function (Grid\Filter $filter) {
                $filter->like('session_id', '会话ID');
                $filter->like('user_email', '用户邮箱');
                $filter->like('goods.gd_name', '商品名称');
                $filter->like('coupon_code', '优惠券代码');
                $filter->between('created_at', '创建时间')->datetime();
            });

            // 禁用新增和编辑
            $grid->disableCreateButton();
            $grid->disableEditButton();

            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->disableEdit();
            });

            // 批量操作
            $grid->batchActions(function (Grid\Tools\BatchActions $batch) {
                $batch->add('清理过期', new \App\Admin\Actions\ClearExpiredCarts());
            });

            // 工具栏
            $grid->tools(function (Grid\Tools $tools) {
                $tools->append('<a href="' . admin_url('shopping-cart/statistics') . '" class="btn btn-sm btn-outline-primary">统计数据</a>');
            });
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id)
    {
        return Show::make($id, new ShoppingCart(), function (Show $show) {
            $show->field('id', 'ID');
            $show->field('session_id', '会话ID');
            $show->field('user_email', '用户邮箱');
            $show->field('goods.gd_name', '商品名称');
            $show->field('goodsSku.name', '规格名称');
            $show->field('quantity', '数量');
            $show->field('price', '单价')->as(function ($price) {
                return '¥' . number_format($price, 2);
            });
            $show->field('total_price', '总价')->as(function ($price) {
                return '¥' . number_format($price, 2);
            });
            $show->field('discount_amount', '优惠金额')->as(function ($amount) {
                return $amount > 0 ? '¥' . number_format($amount, 2) : '-';
            });
            $show->field('coupon_code', '优惠券代码');
            $show->field('goods_snapshot', '商品快照')->json();
            $show->field('sku_snapshot', '规格快照')->json();
            $show->field('custom_fields', '自定义字段')->json();
            $show->field('expires_at', '过期时间');
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');
        });
    }

    /**
     * 购物车统计
     */
    public function statistics()
    {
        $stats = [
            'total_carts' => ShoppingCartModel::count(),
            'active_carts' => ShoppingCartModel::notExpired()->count(),
            'expired_carts' => ShoppingCartModel::expired()->count(),
            'total_value' => ShoppingCartModel::notExpired()->sum('total_price'),
            'avg_cart_value' => ShoppingCartModel::notExpired()->avg('total_price'),
            'today_carts' => ShoppingCartModel::whereDate('created_at', today())->count(),
            'week_carts' => ShoppingCartModel::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'month_carts' => ShoppingCartModel::whereMonth('created_at', now()->month)->count(),
        ];

        // 热门商品统计
        $popularGoods = ShoppingCartModel::notExpired()
            ->selectRaw('goods_id, COUNT(*) as cart_count, SUM(quantity) as total_quantity')
            ->groupBy('goods_id')
            ->orderBy('cart_count', 'desc')
            ->limit(10)
            ->with('goods')
            ->get();

        // 用户购物车统计
        $userStats = ShoppingCartModel::notExpired()
            ->whereNotNull('user_email')
            ->selectRaw('user_email, COUNT(*) as cart_count, SUM(total_price) as total_value')
            ->groupBy('user_email')
            ->orderBy('total_value', 'desc')
            ->limit(10)
            ->get();

        return view('admin.shopping-cart.statistics', compact('stats', 'popularGoods', 'userStats'));
    }

    /**
     * 清理过期购物车
     */
    public function clearExpired()
    {
        $count = ShoppingCartModel::clearExpired();
        
        return response()->json([
            'status' => true,
            'message' => "成功清理 {$count} 个过期购物车项"
        ]);
    }
}
