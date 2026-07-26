<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Customer::withCount('orders')
            ->withSum('orders as total_spent', 'total_amount');

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        $perPage = min((int) $request->integer('per_page', 15), 100);
        return CustomerResource::collection($query->latest()->paginate($perPage));
    }

    public function show(Customer $customer): CustomerResource
    {
        $customer->load(['orders' => fn ($q) => $q->latest()->limit(20)]);
        $customer->loadCount('orders');
        $customer->total_spent = (float) Order::where('customer_id', $customer->id)
            ->whereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_SHIPPED, Order::STATUS_PROCESSING])
            ->sum('total_amount');
        return new CustomerResource($customer);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = Customer::create($data);
        return (new CustomerResource($customer))
            ->additional(['message' => 'Customer created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Customer $customer): CustomerResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:customers,email,'.$customer->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $customer->update($data);
        return (new CustomerResource($customer))->additional(['message' => 'Customer updated.']);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();
        return response()->json(['message' => 'Customer deleted.']);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total'  => Customer::count(),
                'top'    => Customer::withCount('orders')
                    ->orderByDesc('orders_count')
                    ->limit(5)
                    ->get()
                    ->map(fn ($c) => [
                        'id'    => $c->id,
                        'name'  => $c->name,
                        'email' => $c->email,
                        'orders_count' => $c->orders_count,
                    ]),
            ],
        ]);
    }
}
