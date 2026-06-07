<?php

namespace App\Services;

use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductBulkService
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function bulkCreate(User $user, array $items)
    {
        $customerGroups = $this->resolveSupportedCustomerGroups($user);
        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach (array_values($items) as $index => $item) {
            $row = $index + 1;

            try {
                $product = $this->createProduct($user, is_array($item) ? $item : [], $customerGroups);

                $results[] = [
                    'row' => $row,
                    'success' => true,
                    'message' => 'Produk berhasil dibuat',
                    'product' => $product->load('prices.customerGroup'),
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

    public function quickCreate(User $user, array $attributes)
    {
        $customerGroups = $this->resolveSupportedCustomerGroups($user);

        return $this->createProduct($user, $attributes, $customerGroups);
    }

    protected function createProduct(User $user, array $attributes, Collection $customerGroups)
    {
        $validated = $this->validateItem($user, $attributes, $customerGroups);

        return DB::transaction(function () use ($user, $validated, $customerGroups) {
            $product = Product::create([
                'company_id' => $user->company_id,
                'product_code' => $validated['product_code'] ?: $this->generateUniqueProductCode($user->company_id),
                'product_name' => $validated['product_name'],
                'unit' => $validated['unit'],
                'cost_price' => $validated['cost_price'],
                'stock' => 0,
                'status' => $validated['status'],
            ]);

            foreach ($validated['prices'] as $price) {
                ProductPrice::create([
                    'company_id' => $user->company_id,
                    'product_id' => $product->id,
                    'customer_group_id' => $price['customer_group_id'],
                    'selling_price' => $price['selling_price'],
                    'status' => '00',
                ]);
            }

            if ($validated['stock'] > 0) {
                $this->stockService->applyInitialStock(
                    $product,
                    $validated['stock'],
                    $user,
                    'Initial stock from quick or bulk product creation'
                );
            }

            return $product->fresh();
        });
    }

    protected function validateItem(User $user, array $attributes, Collection $customerGroups)
    {
        $validator = Validator::make($attributes, [
            'product_name' => 'required|string|max:255',
            'product_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'product_code')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                }),
            ],
            'unit' => 'nullable|string|max:50',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:00,99',
            'selling_price' => 'nullable|numeric|min:0',
            'prices' => 'nullable|array',
            'prices.*.customer_group_code' => 'required_with:prices|string|in:USER,FREELANCER,GROSIR',
            'prices.*.selling_price' => 'nullable|numeric|min:0',
        ]);

        $validator->setAttributeNames([
            'product_name' => 'Nama produk',
            'product_code' => 'Kode produk',
            'unit' => 'Satuan',
            'cost_price' => 'Harga modal',
            'selling_price' => 'Harga jual',
            'stock' => 'Stok',
            'prices' => 'Daftar harga',
            'prices.*.customer_group_code' => 'Kode customer group',
            'prices.*.selling_price' => 'Harga jual per customer group',
        ]);

        $validator->after(function ($validator) use ($attributes, $customerGroups) {
            $normalizedPrices = $this->normalizeSubmittedPrices($attributes['prices'] ?? []);
            $hasLegacySellingPrice = array_key_exists('selling_price', $attributes) && $attributes['selling_price'] !== null && $attributes['selling_price'] !== '';

            if (! $hasLegacySellingPrice && empty($normalizedPrices['USER'])) {
                $validator->errors()->add('selling_price', 'Harga jual USER wajib diisi.');
            }

            foreach (array_keys($normalizedPrices) as $groupCode) {
                if (! $customerGroups->has($groupCode)) {
                    $validator->errors()->add('prices', 'Customer group '.$groupCode.' belum tersedia.');
                }
            }
        });

        $validated = $validator->validate();
        $normalizedPrices = $this->buildNormalizedPrices(
            $validated,
            $customerGroups
        );

        return [
            'product_name' => $validated['product_name'],
            'product_code' => isset($validated['product_code']) && $validated['product_code'] !== ''
                ? Str::upper(trim($validated['product_code']))
                : null,
            'unit' => isset($validated['unit']) && $validated['unit'] !== ''
                ? Str::upper(trim($validated['unit']))
                : null,
            'cost_price' => isset($validated['cost_price']) ? (float) $validated['cost_price'] : 0,
            'stock' => isset($validated['stock']) ? (float) $validated['stock'] : 0,
            'status' => $validated['status'] ?? '00',
            'prices' => $normalizedPrices,
        ];
    }

    protected function resolveSupportedCustomerGroups(User $user)
    {
        $groups = CustomerGroup::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('status', '00')
            ->whereIn('group_code', ['USER', 'FREELANCER', 'GROSIR'])
            ->orderBy('id')
            ->get()
            ->keyBy(function (CustomerGroup $group) {
                return Str::upper($group->group_code);
            });

        if ($groups->has('USER')) {
            return $groups;
        }

        throw ValidationException::withMessages([
            'customer_group' => ['Customer group USER belum tersedia.'],
        ]);
    }

    protected function generateUniqueProductCode($companyId)
    {
        $prefix = 'PRD-'.now()->format('Ymd').'-';
        $sequence = 1;

        $latestCode = Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_code', 'like', $prefix.'%')
            ->orderByDesc('product_code')
            ->value('product_code');

        if ($latestCode && preg_match('/(\d+)$/', $latestCode, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $exists = Product::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('product_code', $candidate)
                ->exists();
            $sequence++;
        } while ($exists);

        return $candidate;
    }

    protected function normalizeSubmittedPrices(array $prices)
    {
        $normalized = [];

        foreach ($prices as $price) {
            if (! is_array($price) || empty($price['customer_group_code'])) {
                continue;
            }

            $groupCode = Str::upper(trim((string) $price['customer_group_code']));

            if ($groupCode === '') {
                continue;
            }

            $value = $price['selling_price'] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$groupCode] = (float) $value;
        }

        return $normalized;
    }

    protected function buildNormalizedPrices(array $validated, Collection $customerGroups)
    {
        $submittedPrices = $this->normalizeSubmittedPrices($validated['prices'] ?? []);
        $userPrice = array_key_exists('USER', $submittedPrices)
            ? $submittedPrices['USER']
            : (float) ($validated['selling_price'] ?? 0);

        $groupCodes = ['USER', 'FREELANCER', 'GROSIR'];
        $normalizedPrices = [];

        foreach ($groupCodes as $groupCode) {
            if (! $customerGroups->has($groupCode)) {
                continue;
            }

            $sellingPrice = $groupCode === 'USER'
                ? $userPrice
                : ($submittedPrices[$groupCode] ?? $userPrice);

            $normalizedPrices[] = [
                'customer_group_id' => $customerGroups->get($groupCode)->id,
                'customer_group_code' => $groupCode,
                'selling_price' => (float) $sellingPrice,
            ];
        }

        return $normalizedPrices;
    }
}
