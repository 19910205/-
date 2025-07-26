<?php
/**
 * The file was created by Assimon.
 *
 * @author    assimon<ashang@utf8.hk>
 * @copyright assimon<ashang@utf8.hk>
 * @link      http://utf8.hk/
 */
use Illuminate\Support\Facades\Route;


Route::group(['middleware' => ['dujiaoka.boot'],'namespace' => 'Home'], function () {
    // 首页
    Route::get('/', 'HomeController@index');
    // 极验效验
    Route::get('check-geetest', 'HomeController@geetest');
    // 商品详情
    Route::get('buy/{id}', 'HomeController@buy');
    // 提交订单
    Route::post('create-order', 'OrderController@createOrder');
    // 结算页
    Route::get('bill/{orderSN}', 'OrderController@bill');
    // 通过订单号详情页
    Route::get('detail-order-sn/{orderSN}', 'OrderController@detailOrderSN');
    // 订单查询页
    Route::get('order-search', 'OrderController@orderSearch');
    // 检查订单状态
    Route::get('check-order-status/{orderSN}', 'OrderController@checkOrderStatus');
    // 通过订单号查询
    Route::post('search-order-by-sn', 'OrderController@searchOrderBySN');
    // 通过邮箱查询
    Route::post('search-order-by-email', 'OrderController@searchOrderByEmail');
    // 通过浏览器查询
    Route::post('search-order-by-browser', 'OrderController@searchOrderByBrowser');

    // 购物车路由
    Route::prefix('cart')->group(function () {
        Route::get('/', 'ShoppingCartController@index')->name('cart.index');
        Route::post('/add', 'ShoppingCartController@add')->name('cart.add');
        Route::put('/update/{cartItem}', 'ShoppingCartController@update')->name('cart.update');
        Route::delete('/remove/{cartItem}', 'ShoppingCartController@remove')->name('cart.remove');
        Route::delete('/clear', 'ShoppingCartController@clear')->name('cart.clear');
        Route::post('/apply-coupon', 'ShoppingCartController@applyCoupon')->name('cart.apply-coupon');
        Route::delete('/remove-coupon/{cartItem}', 'ShoppingCartController@removeCoupon')->name('cart.remove-coupon');
        Route::get('/total', 'ShoppingCartController@getTotal')->name('cart.total');
        Route::post('/checkout', 'ShoppingCartController@checkout')->name('cart.checkout');
    });

    // 商品规格API
    Route::get('/api/goods/{goods}/skus', function (\App\Models\Goods $goods) {
        return $goods->getAvailableSkus();
    });
});

// 分站API路由（不需要中间件）
Route::prefix('api/subsite')->namespace('Api')->group(function () {
    Route::post('/orders/sync', 'SubsiteController@syncOrder');
    Route::get('/test', 'SubsiteController@test');
    Route::get('/goods', 'SubsiteController@getGoods');
    Route::get('/order-status', 'SubsiteController@getOrderStatus');
});

Route::group(['middleware' => ['install.check'],'namespace' => 'Home'], function () {
    // 安装
    Route::get('install', 'HomeController@install');
    // 执行安装
    Route::post('do-install', 'HomeController@doInstall');
});

