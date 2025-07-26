<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Dcat\Admin\Admin;

Admin::routes();

Route::group([
    'prefix'     => config('admin.route.prefix'),
    'namespace'  => config('admin.route.namespace'),
    'middleware' => config('admin.route.middleware'),
], function (Router $router) {
    $router->get('/', 'HomeController@index');
    $router->resource('goods', 'GoodsController');
    $router->resource('goods-group', 'GoodsGroupController');
    $router->resource('carmis', 'CarmisController');
    $router->resource('coupon', 'CouponController');
    $router->resource('emailtpl', 'EmailtplController');
    $router->resource('pay', 'PayController');
    $router->resource('order', 'OrderController');

    // 分站管理
    $router->resource('subsite', 'SubsiteController');
    $router->get('subsite/{id}/orders', 'SubsiteController@orders');
    $router->get('subsite/{id}/statistics', 'SubsiteController@statistics');

    // 商品规格管理
    $router->resource('goods-sku', 'GoodsSkuController');

    // 购物车管理
    $router->resource('shopping-cart', 'ShoppingCartController');
    $router->get('shopping-cart/statistics', 'ShoppingCartController@statistics');
    $router->post('shopping-cart/clear-expired', 'ShoppingCartController@clearExpired');

    // API路由
    $router->get('api/goods-skus', function () {
        $goodsId = request('q');
        if (!$goodsId) {
            return [];
        }
        return \App\Models\GoodsSku::where('goods_id', $goodsId)
            ->where('status', \App\Models\GoodsSku::STATUS_ENABLED)
            ->pluck('name', 'id');
    });

    $router->get('import-carmis', 'CarmisController@importCarmis');
    $router->get('system-setting', 'SystemSettingController@systemSetting');
    $router->get('email-test', 'EmailTestController@emailTest');
});
