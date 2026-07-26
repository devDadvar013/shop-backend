<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->resource;
        return [
            'range_days'      => (int) $data['range_days'],
            'total_revenue'   => round((float) $data['total_revenue'], 2),
            'orders_count'    => (int) $data['orders_count'],
            'pending_orders'  => (int) $data['pending_orders'],
            'customers_count' => (int) $data['customers_count'],
            'products_count'  => (int) $data['products_count'],
            'low_stock_count' => (int) $data['low_stock_count'],
            'average_order_value' => $data['orders_count'] > 0
                ? round($data['total_revenue'] / $data['orders_count'], 2)
                : 0,
            'status_breakdown' => $data['status_breakdown'] ?? [],
            'daily_revenue'   => $data['daily_revenue'] ?? [],
        ];
    }
}
