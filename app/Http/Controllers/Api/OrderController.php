<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::with(['customer', 'items.product'])->withCount('items')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(function ($q) use ($term) {
                $q->where('order_number', 'like', "%{$term}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")
                                                       ->orWhere('email', 'like', "%{$term}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        $perPage = min((int) $request->integer('per_page', 15), 100);
        $orders = $query->paginate($perPage);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        $order->load(['customer', 'items.product']);
        return new OrderResource($order);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'status' => ['nullable', Rule::in(Order::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = DB::transaction(function () use ($data) {
            $order = Order::create([
                'customer_id'      => $data['customer_id'],
                'status'           => $data['status'] ?? Order::STATUS_PENDING,
                'notes'            => $data['notes'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
            ]);

            $total = 0;
            foreach ($data['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                if ($product->stock < $item['quantity']) {
                    abort(422, "Insufficient stock for product: {$product->name}");
                }
                $unitPrice = $product->price;
                $lineTotal = $unitPrice * $item['quantity'];
                $total += $lineTotal;

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $product->decrement('stock', $item['quantity']);
            }

            $order->update(['total_amount' => $total]);
            $order->load(['customer', 'items.product']);
            return $order;
        });

        return (new OrderResource($order))
            ->additional(['message' => 'Order created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Order $order): OrderResource
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(Order::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
        ]);

        $order->update($data);
        $order->load(['customer', 'items.product']);

        return (new OrderResource($order))
            ->additional(['message' => 'Order updated.']);
    }

    public function updateStatus(Request $request, Order $order): OrderResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
        ]);

        $order->update(['status' => $data['status']]);
        $order->load(['customer', 'items.product']);

        return (new OrderResource($order))
            ->additional(['message' => "Status changed to {$data['status']}."]);
    }

    public function destroy(Order $order): JsonResponse
    {
        // Restore stock on cancellation/delete
        if ($order->status !== Order::STATUS_CANCELLED) {
            DB::transaction(function () use ($order) {
                foreach ($order->items()->get() as $item) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
                $order->delete();
            });
        } else {
            $order->delete();
        }
        return response()->json(['message' => 'Order deleted.']);
    }

    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' => collect(Order::STATUSES)->map(fn ($s) => [
                'value' => $s,
                'label' => ucfirst($s),
                'color' => $this->statusColor($s),
            ])->all(),
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total'    => Order::count(),
                'pending'  => Order::where('status', Order::STATUS_PENDING)->count(),
                'revenue'  => (float) Order::whereIn('status', [
                    Order::STATUS_DELIVERED, Order::STATUS_SHIPPED, Order::STATUS_PROCESSING,
                ])->sum('total_amount'),
                'today'    => Order::whereDate('created_at', today())->count(),
            ],
        ]);
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING    => 'amber',
            Order::STATUS_PROCESSING => 'blue',
            Order::STATUS_SHIPPED    => 'indigo',
            Order::STATUS_DELIVERED  => 'emerald',
            Order::STATUS_CANCELLED  => 'rose',
            default                  => 'slate',
        };
    }
}
