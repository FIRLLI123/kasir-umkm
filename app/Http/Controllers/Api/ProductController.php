<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use ApiResponse;

    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function index(Request $request)
    {
        $query = Product::with(['prices' => function ($relation) {
            $relation->orderBy('customer_group_id');
        }])->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('product_name', 'like', '%'.$search.'%')
                    ->orWhere('product_code', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $products = $query->paginate((int) $request->get('per_page', 10));

        return $this->successResponse($products->items(), 'Daftar produk berhasil diambil', 200, [
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $product = Product::with('prices.customerGroup')->findOrFail($id);

        return $this->successResponse($product, 'Detail produk berhasil diambil');
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $product = DB::transaction(function () use ($validated, $request) {
            $product = Product::create([
                'product_code' => $validated['product_code'] ?? null,
                'product_name' => $validated['product_name'],
                'unit' => $validated['unit'] ?? null,
                'cost_price' => $validated['cost_price'],
                'stock' => 0,
                'status' => $validated['status'] ?? '00',
            ]);

            $this->syncPrices($product, $validated['prices'] ?? []);

            if (isset($validated['stock']) && (float) $validated['stock'] > 0) {
                $this->stockService->applyInitialStock(
                    $product,
                    $validated['stock'],
                    $request->user(),
                    'Initial stock from product creation'
                );
            }

            return $product;
        });

        return $this->successResponse($product->load('prices.customerGroup'), 'Produk berhasil dibuat', 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $validated = $this->validatePayload($request, $product->id);

        DB::transaction(function () use ($product, $validated, $request) {
            $newStock = isset($validated['stock']) ? (float) $validated['stock'] : null;

            $product->update([
                'product_code' => $validated['product_code'] ?? null,
                'product_name' => $validated['product_name'],
                'unit' => $validated['unit'] ?? null,
                'cost_price' => $validated['cost_price'],
                'status' => $validated['status'] ?? $product->status,
            ]);

            $this->syncPrices($product, $validated['prices'] ?? []);

            if ($newStock !== null) {
                $this->stockService->adjustStock(
                    $product->fresh(),
                    $newStock,
                    $request->user(),
                    'Stock adjustment from product update'
                );
            }
        });

        return $this->successResponse($product->fresh()->load('prices.customerGroup'), 'Produk berhasil diupdate');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => '99']);

        ProductPrice::where('product_id', $product->id)->update(['status' => '99']);

        return $this->successResponse($product->fresh()->load('prices.customerGroup'), 'Produk berhasil dinonaktifkan');
    }

    protected function validatePayload(Request $request, $productId = null)
    {
        return $request->validate([
            'product_code' => 'nullable|string|max:50|unique:products,product_code,'.$productId,
            'product_name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'cost_price' => 'required|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:00,99',
            'prices' => 'nullable|array',
            'prices.*.customer_group_id' => 'required_with:prices|distinct|exists:customer_groups,id',
            'prices.*.selling_price' => 'required_with:prices|numeric|min:0',
            'prices.*.status' => 'nullable|in:00,99',
        ]);
    }

    protected function syncPrices(Product $product, array $prices)
    {
        ProductPrice::where('product_id', $product->id)->delete();

        foreach ($prices as $price) {
            ProductPrice::create([
                'product_id' => $product->id,
                'customer_group_id' => $price['customer_group_id'],
                'selling_price' => $price['selling_price'],
                'status' => isset($price['status']) ? $price['status'] : '00',
            ]);
        }
    }
}
