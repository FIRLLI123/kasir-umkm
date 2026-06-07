<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockBulkService
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function bulkStockIn(User $user, array $items, $mutationDate = null)
    {
        $results = [];
        $successCount = 0;
        $failedCount = 0;
        $seenProductIds = [];

        foreach (array_values($items) as $index => $item) {
            $row = $index + 1;

            try {
                $validated = $this->validateItem($user, is_array($item) ? $item : []);

                if (in_array($validated['product_id'], $seenProductIds, true)) {
                    throw ValidationException::withMessages([
                        'product_id' => ['Produk yang sama tidak boleh diinput lebih dari sekali dalam satu submit.'],
                    ]);
                }

                $seenProductIds[] = $validated['product_id'];

                $product = Product::findOrFail($validated['product_id']);
                $stockBefore = (float) $product->stock;
                $newStock = $stockBefore + (float) $validated['qty'];

                $updatedProduct = $this->stockService->adjustStock(
                    $product,
                    $newStock,
                    $user,
                    $validated['note'] ?: 'Bulk stock in from mobile',
                    $mutationDate
                );

                $results[] = [
                    'row' => $row,
                    'success' => true,
                    'message' => 'Stok berhasil ditambahkan',
                    'data' => [
                        'product_id' => $updatedProduct->id,
                        'product_code' => $updatedProduct->product_code,
                        'product_name' => $updatedProduct->product_name,
                        'stock_before' => $stockBefore,
                        'qty_stock_in' => (float) $validated['qty'],
                        'stock_after' => (float) $updatedProduct->stock,
                    ],
                ];
                $successCount++;
            } catch (ValidationException $exception) {
                $results[] = [
                    'row' => $row,
                    'success' => false,
                    'message' => 'Validasi gagal pada baris '.$row,
                    'errors' => $exception->errors(),
                ];
                $failedCount++;
            } catch (HttpResponseException $exception) {
                $payload = (array) $exception->getResponse()->getData(true);

                $results[] = [
                    'row' => $row,
                    'success' => false,
                    'message' => $payload['message'] ?? 'Gagal memproses baris '.$row,
                    'errors' => [
                        'row' => [$payload['message'] ?? 'Gagal memproses penambahan stok.'],
                    ],
                ];
                $failedCount++;
            }
        }

        return [
            'summary' => [
                'total' => count($items),
                'success' => $successCount,
                'failed' => $failedCount,
            ],
            'items' => $results,
        ];
    }

    protected function validateItem(User $user, array $item)
    {
        $validator = Validator::make($item, [
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                }),
            ],
            'qty' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);

        $validator->setAttributeNames([
            'product_id' => 'Produk',
            'qty' => 'Qty tambah',
            'note' => 'Catatan',
        ]);

        return $validator->validate();
    }
}
