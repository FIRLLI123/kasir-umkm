<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMutation;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function applyInitialStock(Product $product, $qty, ?User $user = null, $note = 'Initial stock')
    {
        $qty = (float) $qty;

        if ($qty <= 0) {
            return $product;
        }

        return $this->createMutation(
            $product,
            'INITIAL',
            'PRODUCT',
            $product->id,
            $qty,
            0,
            $note,
            $user
        );
    }

    public function adjustStock(Product $product, $newStock, ?User $user = null, $note = null, $mutationDate = null)
    {
        $currentStock = (float) $product->stock;
        $newStock = (float) $newStock;

        if ($newStock < 0) {
            $this->fail('Stok tidak boleh kurang dari 0');
        }

        if ($newStock === $currentStock) {
            return $product->fresh();
        }

        $difference = $newStock - $currentStock;
        $qtyIn = $difference > 0 ? $difference : 0;
        $qtyOut = $difference < 0 ? abs($difference) : 0;

        return $this->createMutation(
            $product,
            $difference > 0 ? 'ADJUSTMENT_IN' : 'ADJUSTMENT_OUT',
            'PRODUCT',
            $product->id,
            $qtyIn,
            $qtyOut,
            $note ?: 'Manual stock adjustment',
            $user,
            $mutationDate
        );
    }

    public function deductForSale(Product $product, $qty, $referenceId, ?User $user = null, $note = null)
    {
        $qty = (float) $qty;

        if ((float) $product->stock < $qty) {
            $this->fail('Stok produk tidak cukup', [
                'product_id' => $product->id,
                'available_stock' => (float) $product->stock,
                'requested_qty' => $qty,
            ]);
        }

        return $this->createMutation(
            $product,
            'SALE',
            'SALE',
            $referenceId,
            0,
            $qty,
            $note ?: 'Pengurangan stok dari transaksi penjualan',
            $user
        );
    }

    public function restoreFromVoid(Product $product, $qty, $referenceId, ?User $user = null, $note = null)
    {
        return $this->createMutation(
            $product,
            'VOID',
            'SALE_VOID',
            $referenceId,
            (float) $qty,
            0,
            $note ?: 'Pengembalian stok dari void transaksi',
            $user
        );
    }

    public function getStockAsOfDate($date, array $filters = [])
    {
        $asOf = Carbon::parse($date)->endOfDay();

        $query = Product::query()->orderBy('product_name');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('product_name', 'like', '%'.$search.'%')
                    ->orWhere('product_code', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(function (Product $product) use ($asOf) {
            $netMutation = (float) StockMutation::query()
                ->where('product_id', $product->id)
                ->where('mutation_date', '<=', $asOf)
                ->selectRaw('COALESCE(SUM(qty_in - qty_out), 0) as stock_balance')
                ->value('stock_balance');

            return [
                'product_id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'unit' => $product->unit,
                'status' => $product->status,
                'stock_as_of_date' => $netMutation,
                'as_of_date' => $asOf->toDateString(),
            ];
        })->values();
    }

    public function getMutationHistory(Product $product, array $filters = [])
    {
        $query = StockMutation::with('creator')
            ->where('product_id', $product->id)
            ->orderByDesc('mutation_date')
            ->orderByDesc('id');

        if (! empty($filters['start_date'])) {
            $query->whereDate('mutation_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('mutation_date', '<=', $filters['end_date']);
        }

        return $query->get();
    }

    protected function createMutation(
        Product $product,
        $mutationType,
        $referenceType,
        $referenceId,
        $qtyIn,
        $qtyOut,
        $note,
        ?User $user = null,
        $mutationDate = null
    ) {
        return DB::transaction(function () use (
            $product,
            $mutationType,
            $referenceType,
            $referenceId,
            $qtyIn,
            $qtyOut,
            $note,
            $user,
            $mutationDate
        ) {
            $product->refresh();

            $stockBefore = (float) $product->stock;
            $stockAfter = $stockBefore + (float) $qtyIn - (float) $qtyOut;

            if ($stockAfter < 0) {
                $this->fail('Stok akhir tidak boleh kurang dari 0', [
                    'product_id' => $product->id,
                    'stock_before' => $stockBefore,
                    'qty_in' => (float) $qtyIn,
                    'qty_out' => (float) $qtyOut,
                ]);
            }

            StockMutation::create([
                'company_id' => $product->company_id,
                'product_id' => $product->id,
                'mutation_date' => $mutationDate ?: now(),
                'mutation_type' => $mutationType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'note' => $note,
                'created_by' => optional($user)->id,
                'status' => '00',
            ]);

            $product->update(['stock' => $stockAfter]);

            return $product->fresh();
        });
    }

    protected function fail($message, $data = null)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], 422));
    }
}
