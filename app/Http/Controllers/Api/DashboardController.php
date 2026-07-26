<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $days = max(1, (int) $request->integer('range', 30));
        $from = now()->subDays($days)->startOfDay();

        $totalRevenue = (float) Order::whereIn('status', [
            Order::STATUS_DELIVERED, Order::STATUS_SHIPPED, Order::STATUS_PROCESSING,
        ])->where('created_at', '>=', $from)->sum('total_amount');

        $ordersCount    = Order::where('created_at', '>=', $from)->count();
        $pendingCount   = Order::where('status', Order::STATUS_PENDING)->count();
        $customersCount = Customer::count();
        $productsCount  = Product::where('is_active', true)->count();
        $lowStockCount  = Product::where('is_active', true)->where('stock', '<=', 5)->count();

        $statusBreakdown = Order::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $dailyRevenue = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('COUNT(*) as orders')
            )
            ->where('created_at', '>=', $from)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date'   => $r->date,
                'total'  => (float) $r->total,
                'orders' => (int) $r->orders,
            ])
            ->all();

        $data = [
            'range_days'      => $days,
            'total_revenue'   => $totalRevenue,
            'orders_count'    => $ordersCount,
            'pending_orders'  => $pendingCount,
            'customers_count' => $customersCount,
            'products_count'  => $productsCount,
            'low_stock_count' => $lowStockCount,
            'status_breakdown'=> $statusBreakdown,
            'daily_revenue'   => $dailyRevenue,
        ];

        return (new DashboardResource($data))->response();
    }

    public function recentOrders(Request $request): AnonymousResourceCollection
    {
        $limit = min((int) $request->integer('limit', 10), 50);
        return OrderResource::collection(
            Order::with('customer')->latest()->limit($limit)->get()
        );
    }

    public function topProducts(Request $request): JsonResponse
    {
        $limit = min((int) $request->integer('limit', 5), 20);
        $items = DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->select(
                'products.id', 'products.name', 'products.sku',
                DB::raw('SUM(order_items.quantity) as sold'),
                DB::raw('SUM(order_items.line_total) as revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('sold')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $items]);
    }
}
