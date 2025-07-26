<?php

namespace App\Services;

use App\Models\Subsite;
use App\Models\SubsiteOrder;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 分站服务类
 * 处理分站相关的业务逻辑
 * 
 * @author Augment Agent
 */
class SubsiteService
{
    /**
     * 创建分站
     */
    public function createSubsite(array $data): Subsite
    {
        return DB::transaction(function () use ($data) {
            // 生成API密钥
            if ($data['type'] == Subsite::TYPE_LOCAL && empty($data['api_key'])) {
                $data['api_key'] = $this->generateApiKey();
                $data['api_secret'] = $this->generateApiSecret();
            }

            // 设置默认配置
            $data['settings'] = [
                'auto_sync' => true,
                'sync_interval' => 300, // 5分钟
                'max_retry' => 5,
                'timeout' => 30
            ];

            return Subsite::create($data);
        });
    }

    /**
     * 更新分站
     */
    public function updateSubsite(Subsite $subsite, array $data): bool
    {
        return DB::transaction(function () use ($subsite, $data) {
            // 如果是本站分站且没有API密钥，则生成
            if ($data['type'] == Subsite::TYPE_LOCAL && empty($subsite->api_key)) {
                $data['api_key'] = $this->generateApiKey();
                $data['api_secret'] = $this->generateApiSecret();
            }

            return $subsite->update($data);
        });
    }

    /**
     * 删除分站
     */
    public function deleteSubsite(Subsite $subsite): bool
    {
        return DB::transaction(function () use ($subsite) {
            // 删除相关订单记录
            $subsite->orders()->delete();
            
            return $subsite->delete();
        });
    }

    /**
     * 佣金结算
     */
    public function settleCommissions(array $orderIds): array
    {
        return DB::transaction(function () use ($orderIds) {
            $subsiteOrders = SubsiteOrder::whereIn('id', $orderIds)
                ->where('commission_status', SubsiteOrder::COMMISSION_STATUS_PENDING)
                ->with('subsite')
                ->get();

            $totalAmount = 0;
            $settledCount = 0;

            foreach ($subsiteOrders as $subsiteOrder) {
                if ($subsiteOrder->markCommissionSettled()) {
                    // 增加分站余额
                    $subsiteOrder->subsite->addBalance($subsiteOrder->commission_amount);
                    $totalAmount += $subsiteOrder->commission_amount;
                    $settledCount++;
                }
            }

            return [
                'settled_count' => $settledCount,
                'total_amount' => $totalAmount
            ];
        });
    }

    /**
     * 同步订单到分站
     */
    public function syncOrdersToSubsite(Subsite $subsite): array
    {
        if (!$subsite->isThirdParty()) {
            throw new \Exception('只有第三方分站才能同步订单');
        }

        $pendingOrders = $subsite->orders()
            ->where('sync_status', SubsiteOrder::SYNC_STATUS_PENDING)
            ->orWhere(function ($query) {
                $query->where('sync_status', SubsiteOrder::SYNC_STATUS_FAILED)
                      ->where('retry_count', '<', 5)
                      ->where(function ($q) {
                          $q->whereNull('next_retry_at')
                            ->orWhere('next_retry_at', '<=', now());
                      });
            })
            ->with('order')
            ->limit(50)
            ->get();

        $successCount = 0;
        $failedCount = 0;

        foreach ($pendingOrders as $subsiteOrder) {
            try {
                $result = $this->syncSingleOrder($subsite, $subsiteOrder);
                if ($result) {
                    $subsiteOrder->markSyncSuccess();
                    $successCount++;
                } else {
                    $subsiteOrder->markSyncFailed('同步失败');
                    $failedCount++;
                }
            } catch (\Exception $e) {
                $subsiteOrder->markSyncFailed($e->getMessage());
                $failedCount++;
                Log::error('分站订单同步失败', [
                    'subsite_id' => $subsite->id,
                    'order_id' => $subsiteOrder->order_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 更新最后同步时间
        $subsite->updateLastSyncTime();

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'total_count' => $pendingOrders->count()
        ];
    }

    /**
     * 同步单个订单
     */
    protected function syncSingleOrder(Subsite $subsite, SubsiteOrder $subsiteOrder): bool
    {
        $order = $subsiteOrder->order;
        
        $syncData = [
            'order_sn' => $order->order_sn,
            'goods_name' => $order->goods->gd_name,
            'goods_price' => $order->actual_price,
            'quantity' => $order->buy_amount,
            'total_price' => $order->total_price,
            'email' => $order->email,
            'contact' => $order->contact ?? '',
            'status' => $order->status,
            'created_at' => $order->created_at->toISOString()
        ];

        // 如果有SKU信息，添加SKU数据
        if ($order->goods_sku_id && $order->goodsSku) {
            $syncData['sku_code'] = $order->goodsSku->sku_code;
            $syncData['sku_name'] = $order->goodsSku->name;
            $syncData['sku_attributes'] = $order->goodsSku->attributes;
        }

        $response = Http::timeout($subsite->getSetting('timeout', 30))
            ->withHeaders([
                'Authorization' => 'Bearer ' . $subsite->api_key,
                'X-API-Secret' => $subsite->api_secret,
                'Content-Type' => 'application/json'
            ])
            ->post($subsite->api_url . '/api/orders/sync', $syncData);

        if ($response->successful()) {
            $responseData = $response->json();
            
            // 保存分站返回的订单号
            if (isset($responseData['order_sn'])) {
                $subsiteOrder->update(['subsite_order_sn' => $responseData['order_sn']]);
            }

            // 保存同步数据
            $subsiteOrder->setSyncData('response', $responseData);
            
            return true;
        }

        return false;
    }

    /**
     * 测试API连接
     */
    public function testApiConnection(Subsite $subsite): array
    {
        if (!$subsite->api_url) {
            throw new \Exception('API地址未配置');
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $subsite->api_key,
                    'X-API-Secret' => $subsite->api_secret
                ])
                ->get($subsite->api_url . '/api/test');

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'API连接正常',
                    'response_time' => $response->transferStats->getTransferTime(),
                    'data' => $response->json()
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'API连接失败：HTTP ' . $response->status(),
                    'response' => $response->body()
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'API连接异常：' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取分站统计数据
     */
    public function getSubsiteStatistics(Subsite $subsite): array
    {
        $stats = [
            'total_orders' => $subsite->orders()->count(),
            'pending_orders' => $subsite->orders()
                ->where('sync_status', SubsiteOrder::SYNC_STATUS_PENDING)
                ->count(),
            'failed_orders' => $subsite->orders()
                ->where('sync_status', SubsiteOrder::SYNC_STATUS_FAILED)
                ->count(),
            'total_commission' => $subsite->orders()->sum('commission_amount'),
            'settled_commission' => $subsite->orders()
                ->where('commission_status', SubsiteOrder::COMMISSION_STATUS_SETTLED)
                ->sum('commission_amount'),
            'pending_commission' => $subsite->orders()
                ->where('commission_status', SubsiteOrder::COMMISSION_STATUS_PENDING)
                ->sum('commission_amount'),
            'balance' => $subsite->balance,
            'last_sync_at' => $subsite->last_sync_at
        ];

        // 今日统计
        $today = now()->startOfDay();
        $stats['today_orders'] = $subsite->orders()
            ->where('created_at', '>=', $today)
            ->count();
        $stats['today_commission'] = $subsite->orders()
            ->where('created_at', '>=', $today)
            ->sum('commission_amount');

        // 本月统计
        $thisMonth = now()->startOfMonth();
        $stats['month_orders'] = $subsite->orders()
            ->where('created_at', '>=', $thisMonth)
            ->count();
        $stats['month_commission'] = $subsite->orders()
            ->where('created_at', '>=', $thisMonth)
            ->sum('commission_amount');

        return $stats;
    }

    /**
     * 创建分站订单记录
     */
    public function createSubsiteOrder(Order $order): void
    {
        // 获取所有启用的分站
        $subsites = Subsite::where('status', Subsite::STATUS_ENABLED)->get();

        foreach ($subsites as $subsite) {
            // 计算佣金
            $commissionAmount = $subsite->calculateCommission($order->total_price);

            SubsiteOrder::create([
                'subsite_id' => $subsite->id,
                'order_id' => $order->id,
                'commission_amount' => $commissionAmount,
                'sync_data' => [
                    'order_data' => $order->toArray(),
                    'goods_data' => $order->goods->toArray(),
                    'sku_data' => $order->goodsSku ? $order->goodsSku->toArray() : null
                ]
            ]);
        }
    }

    /**
     * 生成API密钥
     */
    protected function generateApiKey(): string
    {
        return 'sk_' . bin2hex(random_bytes(16));
    }

    /**
     * 生成API秘钥
     */
    protected function generateApiSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
