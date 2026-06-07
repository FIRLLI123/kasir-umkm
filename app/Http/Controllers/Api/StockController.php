<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\StockBulkService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    use ApiResponse;

    protected $stockBulkService;

    protected $stockService;

    public function __construct(StockService $stockService, StockBulkService $stockBulkService)
    {
        $this->stockService = $stockService;
        $this->stockBulkService = $stockBulkService;
    }

    public function index(Request $request)
    {
        if ($request->filled('date')) {
            $stocks = $this->stockService->getStockAsOfDate($request->date, $request->only(['search', 'status']));

            return $this->successResponse($stocks, 'Posisi stok per tanggal berhasil diambil');
        }

        $query = Product::query()->orderBy('product_name');

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

        $products = $query->get()->map(function (Product $product) {
            return [
                'product_id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'unit' => $product->unit,
                'current_stock' => (float) $product->stock,
                'status' => $product->status,
            ];
        });

        return $this->successResponse($products, 'Stok barang berhasil diambil');
    }

    public function adjust(Request $request)
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where(function ($query) use ($request) {
                    return $query->where('company_id', $request->user()->company_id);
                }),
            ],
            'new_stock' => 'required|numeric|min:0',
            'note' => 'nullable|string',
            'mutation_date' => 'nullable|date',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        $product = $this->stockService->adjustStock(
            $product,
            $validated['new_stock'],
            $request->user(),
            isset($validated['note']) ? $validated['note'] : null,
            isset($validated['mutation_date']) ? $validated['mutation_date'] : null
        );

        return $this->successResponse($product, 'Stok berhasil diubah');
    }

    public function bulkStockIn(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'mutation_date' => 'nullable|date',
        ]);

        $result = $this->stockBulkService->bulkStockIn(
            $request->user(),
            $validated['items'],
            $validated['mutation_date'] ?? null
        );

        return $this->successResponse($result, 'Bulk penambahan stok selesai diproses');
    }

    public function history(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);
        $history = $this->stockService->getMutationHistory($product, $request->only(['start_date', 'end_date']));

        return $this->successResponse([
            'product' => $product,
            'mutations' => $history,
        ], 'Riwayat stok berhasil diambil');
    }
}
