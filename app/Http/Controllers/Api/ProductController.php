<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->float('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->float('max_price'));
        }
        if ($request->filled('in_stock')) {
            $request->boolean('in_stock') ? $query->where('stock', '>', 0) : $query->where('stock', '<=', 0);
        }

        $sort = $request->string('sort', 'latest');
        match ((string) $sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name'       => $query->orderBy('name', 'asc'),
            'stock'      => $query->orderBy('stock', 'asc'),
            default      => $query->latest(),
        };

        $perPage = min((int) $request->integer('per_page', 15), 100);
        return ProductResource::collection($query->paginate($perPage));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'url'],
            'is_active' => ['boolean'],
        ]);

        $product = Product::create($data);

        return (new ProductResource($product))
            ->additional(['message' => 'Product created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'image_url' => ['nullable', 'url'],
            'is_active' => ['boolean'],
        ]);

        $product->update($data);
        return (new ProductResource($product))->additional(['message' => 'Product updated.']);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted.']);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total'       => Product::count(),
                'active'      => Product::where('is_active', true)->count(),
                'low_stock'   => Product::where('is_active', true)->where('stock', '<=', 5)->count(),
                'out_of_stock'=> Product::where('stock', '<=', 0)->count(),
                'inventory_value' => (float) Product::sum(\DB::raw('price * stock')),
            ],
        ]);
    }
}
