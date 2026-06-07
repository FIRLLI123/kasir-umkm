<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Shuchkin\SimpleXLSX;

class ProductImportService
{
    protected $productBulkService;

    protected $stockService;

    public function __construct(ProductBulkService $productBulkService, StockService $stockService)
    {
        $this->productBulkService = $productBulkService;
        $this->stockService = $stockService;
    }

    public function import(User $user, UploadedFile $file, $mode)
    {
        $rows = $this->parseSpreadsheet($file);

        if (empty($rows)) {
            throw ValidationException::withMessages([
                'file' => ['File import tidak memiliki data.'],
            ]);
        }

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $result = $mode === 'create_products'
                    ? $this->importCreateRow($user, $row)
                    : $this->importStockRow($user, $row);

                $results[] = [
                    'row' => $rowNumber,
                    'success' => true,
                    'message' => $mode === 'create_products'
                        ? 'Produk berhasil diimport'
                        : 'Stok berhasil ditambahkan',
                    'data' => $result,
                ];
                $successCount++;
            } catch (ValidationException $exception) {
                $results[] = [
                    'row' => $rowNumber,
                    'success' => false,
                    'message' => 'Validasi gagal pada baris '.$rowNumber,
                    'errors' => $exception->errors(),
                ];
                $failedCount++;
            } catch (HttpResponseException $exception) {
                $response = $exception->getResponse();
                $payload = method_exists($response, 'getData') ? (array) $response->getData(true) : [];

                $results[] = [
                    'row' => $rowNumber,
                    'success' => false,
                    'message' => Arr::get($payload, 'message', 'Gagal memproses baris '.$rowNumber),
                    'errors' => Arr::get($payload, 'errors') ?: [
                        'row' => [Arr::get($payload, 'message', 'Gagal memproses data import.')],
                    ],
                ];
                $failedCount++;
            }
        }

        return [
            'mode' => $mode,
            'summary' => [
                'total' => count($rows),
                'success' => $successCount,
                'failed' => $failedCount,
            ],
            'items' => $results,
        ];
    }

    protected function importCreateRow(User $user, array $row)
    {
        $attributes = [
            'product_code' => $this->nullableValue($this->value($row, ['product_code', 'kode_produk', 'barcode'])),
            'product_name' => $this->nullableValue($this->value($row, ['product_name', 'nama_produk', 'nama_barang'])),
            'unit' => $this->nullableValue($this->value($row, ['unit', 'satuan'])),
            'cost_price' => $this->numericOrNull($this->value($row, ['cost_price', 'harga_modal', 'modal'])),
            'stock' => $this->numericOrNull($this->value($row, ['stock', 'stok'])),
            'status' => $this->nullableValue($this->value($row, ['status'])),
            'prices' => [
                [
                    'customer_group_code' => 'USER',
                    'selling_price' => $this->numericOrNull($this->value($row, ['price_user', 'harga_user', 'selling_price', 'harga_jual'])),
                ],
                [
                    'customer_group_code' => 'FREELANCER',
                    'selling_price' => $this->numericOrNull($this->value($row, ['price_freelancer', 'harga_freelancer'])),
                ],
                [
                    'customer_group_code' => 'GROSIR',
                    'selling_price' => $this->numericOrNull($this->value($row, ['price_grosir', 'harga_grosir'])),
                ],
            ],
        ];

        $product = $this->productBulkService->quickCreate($user, $attributes);

        return $product->load('prices.customerGroup');
    }

    protected function importStockRow(User $user, array $row)
    {
        $validator = Validator::make($row, [
            'product_code' => 'required|string|max:50',
            'qty_stock_in' => 'required|numeric|min:0.01',
        ]);

        $validator->setAttributeNames([
            'product_code' => 'Kode produk',
            'qty_stock_in' => 'Qty stock in',
        ]);

        $validated = $validator->validate();

        $product = Product::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('product_code', Str::upper(trim($validated['product_code'])))
            ->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product_code' => ['Produk dengan kode tersebut tidak ditemukan.'],
            ]);
        }

        $newStock = (float) $product->stock + (float) $validated['qty_stock_in'];

        $updatedProduct = $this->stockService->adjustStock(
            $product,
            $newStock,
            $user,
            'Stock in from import file'
        );

        return [
            'product_id' => $updatedProduct->id,
            'product_code' => $updatedProduct->product_code,
            'product_name' => $updatedProduct->product_name,
            'stock_before' => (float) $product->stock,
            'qty_stock_in' => (float) $validated['qty_stock_in'],
            'stock_after' => (float) $updatedProduct->stock,
        ];
    }

    protected function parseSpreadsheet(UploadedFile $file)
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsv($file->getRealPath());
        }

        if ($extension === 'xlsx') {
            return $this->parseXlsx($file->getRealPath());
        }

        throw ValidationException::withMessages([
            'file' => ['Format file tidak didukung. Gunakan file .xlsx atau .csv'],
        ]);
    }

    protected function parseCsv($path)
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw ValidationException::withMessages([
                'file' => ['File CSV tidak bisa dibaca.'],
            ]);
        }

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if ($headers === null) {
                $headers = $this->normalizeHeaders($data);
                continue;
            }

            if ($this->isEmptyRow($data)) {
                continue;
            }

            $rows[] = $this->combineRow($headers, $data);
        }

        fclose($handle);

        return $rows;
    }

    protected function parseXlsx($path)
    {
        if (! class_exists(SimpleXLSX::class)) {
            throw ValidationException::withMessages([
                'file' => ['Library pembaca file Excel belum tersedia di server.'],
            ]);
        }

        $xlsx = SimpleXLSX::parse($path);

        if (! $xlsx) {
            throw ValidationException::withMessages([
                'file' => ['File Excel tidak bisa dibaca.'],
            ]);
        }

        $sheetRows = $xlsx->rows();

        if (empty($sheetRows)) {
            return [];
        }

        $headers = $this->normalizeHeaders(array_shift($sheetRows));
        $rows = [];

        foreach ($sheetRows as $data) {
            if ($this->isEmptyRow($data)) {
                continue;
            }

            $rows[] = $this->combineRow($headers, $data);
        }

        return $rows;
    }

    protected function normalizeHeaders(array $headers)
    {
        return array_map(function ($header) {
            $header = trim((string) $header);
            $header = Str::lower($header);
            $header = str_replace([' ', '-'], '_', $header);

            return $header;
        }, $headers);
    }

    protected function combineRow(array $headers, array $data)
    {
        $row = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $row[$header] = array_key_exists($index, $data) ? trim((string) $data[$index]) : null;
        }

        if (isset($row['kode_produk']) && ! isset($row['product_code'])) {
            $row['product_code'] = $row['kode_produk'];
        }

        if (isset($row['nama_produk']) && ! isset($row['product_name'])) {
            $row['product_name'] = $row['nama_produk'];
        }

        if (isset($row['satuan']) && ! isset($row['unit'])) {
            $row['unit'] = $row['satuan'];
        }

        if (isset($row['stok_masuk']) && ! isset($row['qty_stock_in'])) {
            $row['qty_stock_in'] = $row['stok_masuk'];
        }

        if (isset($row['qty']) && ! isset($row['qty_stock_in'])) {
            $row['qty_stock_in'] = $row['qty'];
        }

        return $row;
    }

    protected function isEmptyRow(array $data)
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function value(array $row, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }

    protected function nullableValue($value)
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : $value;
    }

    protected function numericOrNull($value)
    {
        $value = $this->nullableValue($value);

        if ($value === null) {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }
}
