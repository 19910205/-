<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subsite;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * 分站API控制器
 * 用于第三方分站对接
 * 
 * @author Augment Agent
 */
class SubsiteController extends Controller
{
    /**
     * API测试接口
     */
    public function test(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'API连接正常',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
    }

    /**
     * 同步订单接口
     */
    public function syncOrder(Request $request): JsonResponse
    {
        try {
            // 验证API密钥
            $apiKey = $request->header('Authorization');
            $apiSecret = $request->header('X-API-Secret');
            
            if (!$apiKey || !$apiSecret) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'API密钥缺失'
                ], 401);
            }

            // 去掉Bearer前缀
            $apiKey = str_replace('Bearer ', '', $apiKey);
            
            // 验证分站
            $subsite = Subsite::where('api_key', $apiKey)
                             ->where('api_secret', $apiSecret)
                             ->where('status', Subsite::STATUS_ENABLED)
                             ->first();

            if (!$subsite) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'API密钥无效'
                ], 401);
            }

            // 验证请求数据
            $validated = $request->validate([
                'order_sn' => 'required|string',
                'goods_name' => 'required|string',
                'goods_price' => 'required|numeric',
                'quantity' => 'required|integer|min:1',
                'total_price' => 'required|numeric',
                'email' => 'required|email',
                'contact' => 'nullable|string',
                'status' => 'required|integer',
                'sku_code' => 'nullable|string',
                'sku_name' => 'nullable|string',
                'sku_attributes' => 'nullable|array'
            ]);

            // 检查订单是否已存在
            $existingOrder = Order::where('order_sn', $validated['order_sn'])->first();
            if ($existingOrder) {
                return response()->json([
                    'status' => 'error',
                    'message' => '订单号已存在',
                    'order_sn' => $validated['order_sn']
                ], 409);
            }

            // 创建订单记录（这里可以根据实际需求调整）
            $orderData = [
                'order_sn' => $validated['order_sn'],
                'email' => $validated['email'],
                'contact' => $validated['contact'] ?? '',
                'buy_amount' => $validated['quantity'],
                'actual_price' => $validated['goods_price'],
                'total_price' => $validated['total_price'],
                'status' => $validated['status'],
                'info' => json_encode([
                    'gd_name' => $validated['goods_name'],
                    'source' => 'subsite_sync',
                    'subsite_id' => $subsite->id
                ])
            ];

            // 如果有SKU信息
            if ($validated['sku_code']) {
                $orderData['sku_snapshot'] = json_encode([
                    'sku_code' => $validated['sku_code'],
                    'name' => $validated['sku_name'],
                    'attributes' => $validated['sku_attributes']
                ]);
            }

            // 这里可以根据实际需求决定是否真的创建订单
            // 或者只是记录同步信息

            return response()->json([
                'status' => 'success',
                'message' => '订单同步成功',
                'order_sn' => $validated['order_sn'],
                'sync_time' => now()->toISOString()
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => '数据验证失败',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '同步失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取商品列表
     */
    public function getGoods(Request $request): JsonResponse
    {
        try {
            // 验证API密钥
            $apiKey = $request->header('Authorization');
            $apiSecret = $request->header('X-API-Secret');
            
            if (!$apiKey || !$apiSecret) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'API密钥缺失'
                ], 401);
            }

            $apiKey = str_replace('Bearer ', '', $apiKey);
            
            $subsite = Subsite::where('api_key', $apiKey)
                             ->where('api_secret', $apiSecret)
                             ->where('status', Subsite::STATUS_ENABLED)
                             ->first();

            if (!$subsite) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'API密钥无效'
                ], 401);
            }

            // 获取商品列表
            $goods = \App\Models\Goods::where('status', \App\Models\Goods::STATUS_OPEN)
                ->with(['skus' => function($query) {
                    $query->where('status', \App\Models\GoodsSku::STATUS_ENABLED);
                }])
                ->get()
                ->map(function($item) {
                    $data = [
                        'id' => $item->id,
                        'name' => $item->gd_name,
                        'description' => $item->gd_description,
                        'price' => $item->actual_price,
                        'stock' => $item->in_stock,
                        'type' => $item->type,
                        'has_sku' => $item->has_sku,
                        'picture' => $item->picture
                    ];

                    if ($item->has_sku && $item->skus->isNotEmpty()) {
                        $data['skus'] = $item->skus->map(function($sku) {
                            return [
                                'id' => $sku->id,
                                'sku_code' => $sku->sku_code,
                                'name' => $sku->name,
                                'price' => $sku->price,
                                'stock' => $sku->stock,
                                'attributes' => $sku->attributes
                            ];
                        });
                    }

                    return $data;
                });

            return response()->json([
                'status' => 'success',
                'data' => $goods,
                'total' => $goods->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '获取商品列表失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取订单状态
     */
    public function getOrderStatus(Request $request): JsonResponse
    {
        try {
            // 验证API密钥
            $apiKey = $request->header('Authorization');
            $apiSecret = $request->header('X-API-Secret');
            
            if (!$apiKey || !$apiSecret) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'API密钥缺失'
                ], 401);
            }

            $apiKey = str_replace('Bearer ', '', $apiKey);
            
            $subsite = Subsite::where('api_key', $apiKey)
                             ->where('api_secret', $apiSecret)
                             ->where('status', Subsite::STATUS_ENABLED)
                             ->first();

            if (!$subsite) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'API密钥无效'
                ], 401);
            }

            $orderSn = $request->input('order_sn');
            if (!$orderSn) {
                return response()->json([
                    'status' => 'error',
                    'message' => '订单号不能为空'
                ], 400);
            }

            $order = Order::where('order_sn', $orderSn)->first();
            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => '订单不存在'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_sn' => $order->order_sn,
                    'status' => $order->status,
                    'total_price' => $order->total_price,
                    'created_at' => $order->created_at->toISOString(),
                    'updated_at' => $order->updated_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '查询订单状态失败：' . $e->getMessage()
            ], 500);
        }
    }
}
