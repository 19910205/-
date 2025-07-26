<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subsite;
use App\Models\SubsiteOrder;
use App\Services\SubsiteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * 分站管理控制器
 * 用于管理分站和第三方对接
 * 
 * @author Augment Agent
 */
class SubsiteController extends Controller
{
    protected SubsiteService $subsiteService;

    public function __construct(SubsiteService $subsiteService)
    {
        $this->subsiteService = $subsiteService;
    }

    /**
     * 分站列表
     */
    public function index(Request $request)
    {
        $query = Subsite::query();

        // 搜索条件
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $subsites = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.subsite.index', compact('subsites'));
    }

    /**
     * 创建分站页面
     */
    public function create()
    {
        return view('admin.subsite.create');
    }

    /**
     * 保存分站
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:subsites,domain',
            'subdomain' => 'nullable|string|unique:subsites,subdomain',
            'type' => 'required|integer|in:1,2',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'api_url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'description' => 'nullable|string'
        ]);

        try {
            $subsite = $this->subsiteService->createSubsite($validated);
            return response()->json([
                'code' => 200,
                'message' => '分站创建成功',
                'data' => $subsite
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '创建失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 编辑分站页面
     */
    public function edit(Subsite $subsite)
    {
        return view('admin.subsite.edit', compact('subsite'));
    }

    /**
     * 更新分站
     */
    public function update(Request $request, Subsite $subsite): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:subsites,domain,' . $subsite->id,
            'subdomain' => 'nullable|string|unique:subsites,subdomain,' . $subsite->id,
            'type' => 'required|integer|in:1,2',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'api_url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'description' => 'nullable|string'
        ]);

        try {
            $this->subsiteService->updateSubsite($subsite, $validated);
            return response()->json([
                'code' => 200,
                'message' => '分站更新成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '更新失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 删除分站
     */
    public function destroy(Subsite $subsite): JsonResponse
    {
        try {
            $this->subsiteService->deleteSubsite($subsite);
            return response()->json([
                'code' => 200,
                'message' => '分站删除成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '删除失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 切换分站状态
     */
    public function toggleStatus(Subsite $subsite): JsonResponse
    {
        try {
            $newStatus = $subsite->status === Subsite::STATUS_ENABLED 
                ? Subsite::STATUS_DISABLED 
                : Subsite::STATUS_ENABLED;
            
            $subsite->update(['status' => $newStatus]);
            
            return response()->json([
                'code' => 200,
                'message' => '状态更新成功',
                'status' => $newStatus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '状态更新失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 分站订单列表
     */
    public function orders(Subsite $subsite, Request $request)
    {
        $query = $subsite->orders()->with(['order', 'order.goods']);

        // 搜索条件
        if ($request->filled('order_sn')) {
            $query->whereHas('order', function($q) use ($request) {
                $q->where('order_sn', 'like', '%' . $request->order_sn . '%');
            });
        }

        if ($request->filled('commission_status')) {
            $query->where('commission_status', $request->commission_status);
        }

        if ($request->filled('sync_status')) {
            $query->where('sync_status', $request->sync_status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.subsite.orders', compact('subsite', 'orders'));
    }

    /**
     * 佣金结算
     */
    public function settleCommission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:subsite_orders,id'
        ]);

        try {
            $result = $this->subsiteService->settleCommissions($validated['order_ids']);
            return response()->json([
                'code' => 200,
                'message' => '佣金结算成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '结算失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 同步订单到分站
     */
    public function syncOrders(Subsite $subsite): JsonResponse
    {
        try {
            $result = $this->subsiteService->syncOrdersToSubsite($subsite);
            return response()->json([
                'code' => 200,
                'message' => '同步成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '同步失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 测试API连接
     */
    public function testApi(Subsite $subsite): JsonResponse
    {
        try {
            $result = $this->subsiteService->testApiConnection($subsite);
            return response()->json([
                'code' => 200,
                'message' => 'API连接测试成功',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'API连接测试失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 分站统计数据
     */
    public function statistics(Subsite $subsite): JsonResponse
    {
        try {
            $stats = $this->subsiteService->getSubsiteStatistics($subsite);
            return response()->json([
                'code' => 200,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '获取统计数据失败：' . $e->getMessage()
            ]);
        }
    }
}
