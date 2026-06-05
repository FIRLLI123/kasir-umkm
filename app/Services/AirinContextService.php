<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SalesHeader;
use App\Models\StockMutation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AirinContextService
{
    protected $user;

    protected $companyId;

    public function build(User $user, $message)
    {
        $this->user = $user;
        $this->companyId = $user->company_id;

        $normalized = Str::lower(trim($message));
        $dateContext = $this->extractDateContext($normalized);
        $blocks = [];
        $intent = 'general';

        if ($this->isProductListQuestion($normalized)) {
            $intent = 'product_list';
            $blocks[] = $this->productListContext();
        }

        if ($this->isTotalStockQuestion($normalized)) {
            $intent = 'stock_total';
            $blocks[] = $this->totalStockContext($dateContext);
        }

        if ($this->isProductStockQuestion($normalized)) {
            $intent = 'stock_product';
            $productContext = $this->productStockContext($normalized, $dateContext);

            if ($productContext) {
                $blocks[] = $productContext;
            }
        }

        if ($this->isTopCustomerQuestion($normalized)) {
            $intent = 'top_customer';
            $blocks[] = $this->topCustomerContext($dateContext);
        }

        if ($this->isCustomerListQuestion($normalized)) {
            $intent = 'customer_list';
            $blocks[] = $this->customerListContext();
        }

        if ($this->isTopProductQuestion($normalized)) {
            $intent = 'top_product';
            $blocks[] = $this->topProductContext($dateContext);
        }

        if ($this->isMarginQuestion($normalized) || $this->isOmzetQuestion($normalized)) {
            $intent = 'sales_summary';
            $blocks[] = $this->salesSummaryContext($dateContext);
        }

        if ($this->isPaymentMethodQuestion($normalized)) {
            $intent = 'payment_method_summary';
            $blocks[] = $this->paymentMethodContext($dateContext);
        }

        $blocks = array_values(array_filter($blocks));

        return [
            'intent' => $intent,
            'has_context' => count($blocks) > 0,
            'context' => $blocks,
            'date_context' => $dateContext,
        ];
    }

    protected function productListContext()
    {
        $totalProducts = (int) Product::query()
            ->where('company_id', $this->companyId)
            ->where('status', '00')
            ->count();
        $products = Product::query()
            ->where('company_id', $this->companyId)
            ->where('status', '00')
            ->orderBy('product_name')
            ->limit(20)
            ->get(['id', 'product_code', 'product_name', 'unit', 'stock']);

        if ($products->isEmpty()) {
            return 'Data produk aktif saat ini belum ada.';
        }

        $lines = $products->map(function (Product $product) {
            return sprintf(
                '- %s (%s), unit %s, stok %s',
                $product->product_name,
                $product->product_code ?: '-',
                $product->unit ?: '-',
                $this->formatNumber($product->stock)
            );
        })->implode("\n");

        return "Daftar produk aktif saat ini:\n".$lines."\nTotal produk aktif: ".$totalProducts.'.';
    }

    protected function totalStockContext(array $dateContext)
    {
        if ($dateContext['type'] === 'as_of_date') {
            $asOfDate = $dateContext['date']->toDateString();
            $rows = Product::query()
                ->where('company_id', $this->companyId)
                ->orderBy('product_name')
                ->get()
                ->map(function (Product $product) use ($dateContext) {
                    $balance = (float) StockMutation::query()
                        ->where('company_id', $this->companyId)
                        ->where('product_id', $product->id)
                        ->where('mutation_date', '<=', $dateContext['date']->copy()->endOfDay())
                        ->selectRaw('COALESCE(SUM(qty_in - qty_out), 0) as stock_balance')
                        ->value('stock_balance');

                    return [
                        'product_name' => $product->product_name,
                        'stock' => $balance,
                    ];
                });

            $totalStock = $rows->sum('stock');

            return 'Total stok seluruh produk per tanggal '.$asOfDate.' adalah '.$this->formatNumber($totalStock).'.';
        }

        $totalStock = (float) Product::query()
            ->where('company_id', $this->companyId)
            ->sum('stock');
        $totalProducts = (int) Product::query()
            ->where('company_id', $this->companyId)
            ->where('status', '00')
            ->count();

        return 'Total stok seluruh produk saat ini adalah '.$this->formatNumber($totalStock).'. Total produk aktif: '.$totalProducts.'.';
    }

    protected function productStockContext($normalizedMessage, array $dateContext)
    {
        $product = $this->findBestMatchingProduct($normalizedMessage);

        if (! $product) {
            return 'Airin belum menemukan produk yang dimaksud dari pertanyaan user. Jika perlu, minta user menyebut nama produk lebih spesifik.';
        }

        if ($dateContext['type'] === 'as_of_date') {
            $asOfDate = $dateContext['date']->toDateString();
            $stock = (float) StockMutation::query()
                ->where('company_id', $this->companyId)
                ->where('product_id', $product->id)
                ->where('mutation_date', '<=', $dateContext['date']->copy()->endOfDay())
                ->selectRaw('COALESCE(SUM(qty_in - qty_out), 0) as stock_balance')
                ->value('stock_balance');

            return sprintf(
                'Stok produk %s per tanggal %s adalah %s %s.',
                $product->product_name,
                $asOfDate,
                $this->formatNumber($stock),
                $product->unit ?: ''
            );
        }

        return sprintf(
            'Stok produk %s saat ini adalah %s %s.',
            $product->product_name,
            $this->formatNumber($product->stock),
            $product->unit ?: ''
        );
    }

    protected function topCustomerContext(array $dateContext)
    {
        $range = $this->resolveRange($dateContext, 'all_time');

        $query = SalesHeader::query()
            ->join('customers', 'customers.id', '=', 'sales_h.customer_id')
            ->where('sales_h.company_id', $this->companyId)
            ->where('customers.company_id', $this->companyId)
            ->where('sales_h.status', '00')
            ->select(
                'customers.customer_name',
                DB::raw('COUNT(sales_h.id) as total_transactions'),
                DB::raw('SUM(sales_h.grand_total) as total_sales')
            )
            ->groupBy('customers.customer_name')
            ->orderByDesc('total_transactions')
            ->orderByDesc('total_sales')
            ->limit(5);

        if (! $range['all_time']) {
            $query->whereBetween(DB::raw('DATE(sales_h.invoice_date)'), [$range['start'], $range['end']]);
        }

        $customers = $query->get();

        if ($customers->isEmpty()) {
            return 'Belum ada data transaksi customer pada periode '.$range['label'].'.';
        }

        $lines = $customers->map(function ($customer, $index) {
            return sprintf(
                '%d. %s - %d transaksi, total belanja %s',
                $index + 1,
                $customer->customer_name,
                (int) $customer->total_transactions,
                $this->formatCurrency($customer->total_sales)
            );
        })->implode("\n");

        return "Customer dengan order terbanyak untuk periode ".$range['label'].":\n".$lines;
    }

    protected function customerListContext()
    {
        $totalCustomers = (int) Customer::query()
            ->where('company_id', $this->companyId)
            ->where('status', '00')
            ->count();
        $customers = Customer::query()
            ->where('company_id', $this->companyId)
            ->where('status', '00')
            ->orderBy('customer_name')
            ->limit(20)
            ->get(['customer_code', 'customer_name', 'phone']);

        if ($customers->isEmpty()) {
            return 'Data customer aktif saat ini belum ada.';
        }

        $lines = $customers->map(function (Customer $customer) {
            return sprintf(
                '- %s (%s), telp %s',
                $customer->customer_name,
                $customer->customer_code ?: '-',
                $customer->phone ?: '-'
            );
        })->implode("\n");

        return "Daftar customer aktif saat ini:\n".$lines."\nTotal customer aktif: ".$totalCustomers.'.';
    }

    protected function topProductContext(array $dateContext)
    {
        $range = $this->resolveRange($dateContext, 'all_time');

        $query = DB::table('sales_d')
            ->join('sales_h', 'sales_h.id', '=', 'sales_d.sales_h_id')
            ->where('sales_h.company_id', $this->companyId)
            ->where('sales_h.status', '00')
            ->select(
                'sales_d.product_name_snapshot as product_name',
                DB::raw('SUM(sales_d.qty) as qty_sold'),
                DB::raw('SUM(sales_d.subtotal) as total_sales')
            )
            ->groupBy('sales_d.product_name_snapshot')
            ->orderByDesc('qty_sold')
            ->limit(5);

        if (! $range['all_time']) {
            $query->whereBetween(DB::raw('DATE(sales_h.invoice_date)'), [$range['start'], $range['end']]);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            return 'Belum ada data produk terjual pada periode '.$range['label'].'.';
        }

        $lines = collect($products)->map(function ($product, $index) {
            return sprintf(
                '%d. %s - qty %s, omzet %s',
                $index + 1,
                $product->product_name,
                $this->formatNumber($product->qty_sold),
                $this->formatCurrency($product->total_sales)
            );
        })->implode("\n");

        return "Produk paling laku untuk periode ".$range['label'].":\n".$lines;
    }

    protected function salesSummaryContext(array $dateContext)
    {
        $range = $this->resolveRange($dateContext);

        $summary = SalesHeader::query()
            ->where('company_id', $this->companyId)
            ->whereBetween(DB::raw('DATE(invoice_date)'), [$range['start'], $range['end']])
            ->selectRaw('
                COUNT(CASE WHEN status = "00" THEN 1 END) as total_transactions,
                COALESCE(SUM(CASE WHEN status = "00" THEN grand_total ELSE 0 END), 0) as omzet,
                COALESCE(SUM(CASE WHEN status = "00" THEN total_margin ELSE 0 END), 0) as margin,
                COALESCE(SUM(CASE WHEN status = "00" THEN total_modal ELSE 0 END), 0) as modal
            ')
            ->first();

        return sprintf(
            'Ringkasan penjualan periode %s: total transaksi %d, omzet %s, modal %s, margin %s.',
            $range['label'],
            (int) $summary->total_transactions,
            $this->formatCurrency($summary->omzet),
            $this->formatCurrency($summary->modal),
            $this->formatCurrency($summary->margin)
        );
    }

    protected function paymentMethodContext(array $dateContext)
    {
        $range = $this->resolveRange($dateContext, 'all_time');

        $query = DB::table('payment_methods')
            ->leftJoin('sales_h', function ($join) use ($range) {
                $join->on('payment_methods.id', '=', 'sales_h.payment_method_id')
                    ->where('sales_h.company_id', '=', $this->companyId)
                    ->where('sales_h.status', '=', '00');

                if (! $range['all_time']) {
                    $join->whereBetween(DB::raw('DATE(sales_h.invoice_date)'), [$range['start'], $range['end']]);
                }
            })
            ->where('payment_methods.company_id', $this->companyId)
            ->select(
                'payment_methods.method_name',
                DB::raw('COUNT(sales_h.id) as total_transactions'),
                DB::raw('COALESCE(SUM(sales_h.grand_total), 0) as total_amount')
            )
            ->groupBy('payment_methods.id', 'payment_methods.method_name')
            ->orderByDesc('total_transactions')
            ->orderByDesc('total_amount')
            ->limit(5);

        $methods = $query->get();

        if ($methods->isEmpty()) {
            return 'Belum ada data metode pembayaran pada periode '.$range['label'].'.';
        }

        $lines = collect($methods)->map(function ($method, $index) {
            return sprintf(
                '%d. %s - %d transaksi, nominal %s',
                $index + 1,
                $method->method_name,
                (int) $method->total_transactions,
                $this->formatCurrency($method->total_amount)
            );
        })->implode("\n");

        return "Metode pembayaran untuk periode ".$range['label'].":\n".$lines;
    }

    protected function extractDateContext($message)
    {
        if (Str::contains($message, 'hari ini')) {
            return [
                'type' => 'specific_day',
                'date' => now(),
            ];
        }

        if (Str::contains($message, 'kemarin')) {
            return [
                'type' => 'specific_day',
                'date' => now()->subDay(),
            ];
        }

        if (Str::contains($message, 'minggu ini')) {
            return [
                'type' => 'week',
                'start' => now()->copy()->startOfWeek(),
                'end' => now()->copy()->endOfWeek(),
            ];
        }

        if (Str::contains($message, 'bulan ini')) {
            return [
                'type' => 'month',
                'start' => now()->copy()->startOfMonth(),
                'end' => now()->copy()->endOfMonth(),
            ];
        }

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $message, $matches)) {
            return [
                'type' => 'as_of_date',
                'date' => Carbon::parse($matches[1]),
            ];
        }

        if (preg_match('/\b(\d{2})[\/\-](\d{2})[\/\-](\d{4})\b/', $message, $matches)) {
            return [
                'type' => 'as_of_date',
                'date' => Carbon::createFromFormat('d-m-Y', $matches[1].'-'.$matches[2].'-'.$matches[3]),
            ];
        }

        return [
            'type' => 'current',
        ];
    }

    protected function resolveRange(array $dateContext, $defaultMode = 'today')
    {
        switch ($dateContext['type']) {
            case 'specific_day':
            case 'as_of_date':
                $date = $dateContext['date']->toDateString();

                return [
                    'start' => $date,
                    'end' => $date,
                    'label' => $date,
                    'all_time' => false,
                ];
            case 'week':
                return [
                    'start' => $dateContext['start']->toDateString(),
                    'end' => $dateContext['end']->toDateString(),
                    'label' => $dateContext['start']->toDateString().' s/d '.$dateContext['end']->toDateString(),
                    'all_time' => false,
                ];
            case 'month':
                return [
                    'start' => $dateContext['start']->toDateString(),
                    'end' => $dateContext['end']->toDateString(),
                    'label' => $dateContext['start']->format('F Y'),
                    'all_time' => false,
                ];
            default:
                if ($defaultMode === 'all_time') {
                    return [
                        'start' => null,
                        'end' => null,
                        'label' => 'semua waktu',
                        'all_time' => true,
                    ];
                }

                return [
                    'start' => now()->toDateString(),
                    'end' => now()->toDateString(),
                    'label' => now()->toDateString(),
                    'all_time' => false,
                ];
        }
    }

    protected function findBestMatchingProduct($message)
    {
        $cleaned = Str::lower($message);
        $stopWords = [
            'stok', 'stock', 'produk', 'barang', 'berapa', 'brp', 'nya', 'saya',
            'di', 'tanggal', 'per', 'pada', 'untuk', 'saat', 'ini', 'hari', 'apa',
            'ada', 'yang', 'total', 'berapakah', 'cek', 'tolong', 'nama', 'list',
            'laku', 'terlaris', 'paling', 'berapa?', 'dong'
        ];

        $tokens = collect(preg_split('/[^a-zA-Z0-9]+/', $cleaned))
            ->filter(function ($token) use ($stopWords) {
                return $token && strlen($token) >= 2 && ! in_array($token, $stopWords, true);
            })
            ->values();

        if ($tokens->isEmpty()) {
            return null;
        }

        $query = Product::query()
            ->where('company_id', $this->companyId)
            ->where('status', '00');

        $query->where(function ($builder) use ($tokens) {
            foreach ($tokens as $token) {
                $builder->orWhere('product_name', 'like', '%'.$token.'%')
                    ->orWhere('product_code', 'like', '%'.$token.'%');
            }
        });

        $products = $query->get();

        if ($products->isEmpty()) {
            return null;
        }

        $best = $products->sortByDesc(function (Product $product) use ($tokens) {
            $score = 0;
            $name = Str::lower($product->product_name.' '.$product->product_code);

            foreach ($tokens as $token) {
                if (Str::contains($name, $token)) {
                    $score += strlen($token);
                }

                if ($name === $token) {
                    $score += 100;
                }
            }

            return $score;
        })->first();

        $bestName = Str::lower($best->product_name.' '.$best->product_code);
        $matchedTokens = $tokens->filter(function ($token) use ($bestName) {
            return Str::contains($bestName, $token);
        });

        return $matchedTokens->isNotEmpty() ? $best : null;
    }

    protected function isProductListQuestion($message)
    {
        return Str::contains($message, [
            'nama produk', 'produk apa aja', 'barang apa aja', 'daftar produk', 'list produk',
        ]);
    }

    protected function isTotalStockQuestion($message)
    {
        return Str::contains($message, [
            'stok total', 'total stok', 'jumlah stok', 'stok saya berapa', 'stok keseluruhan',
        ]);
    }

    protected function isProductStockQuestion($message)
    {
        return Str::contains($message, ['stok', 'stock']) && ! $this->isTotalStockQuestion($message);
    }

    protected function isTopCustomerQuestion($message)
    {
        return Str::contains($message, [
            'customer yang paling banyak order',
            'customer terbanyak order',
            'customer terbaik',
            'pelanggan terbaik',
            'customer paling banyak beli',
            'customer paling sering beli',
            'pelanggan paling banyak order',
        ]);
    }

    protected function isCustomerListQuestion($message)
    {
        return Str::contains($message, [
            'nama customer', 'nama pelanggan', 'customer saya siapa aja', 'pelanggan saya siapa aja',
            'daftar customer', 'daftar pelanggan', 'list customer', 'list pelanggan',
        ]);
    }

    protected function isTopProductQuestion($message)
    {
        return Str::contains($message, [
            'produk yang paling banyak laku',
            'produk paling laku',
            'produk terlaris',
            'barang terlaris',
            'top produk',
        ]);
    }

    protected function isMarginQuestion($message)
    {
        return Str::contains($message, [
            'margin', 'laba', 'profit',
        ]);
    }

    protected function isOmzetQuestion($message)
    {
        return Str::contains($message, [
            'omzet', 'pendapatan', 'penjualan hari ini', 'sales hari ini', 'omset',
            'penjualan tanggal', 'omzet tanggal',
        ]);
    }

    protected function isPaymentMethodQuestion($message)
    {
        return Str::contains($message, [
            'metode pembayaran', 'pembayaran paling banyak', 'payment method',
            'bayar paling sering', 'metode bayar paling sering',
        ]);
    }

    protected function formatCurrency($value)
    {
        return 'Rp'.number_format((float) $value, 0, ',', '.');
    }

    protected function formatNumber($value)
    {
        $float = (float) $value;

        if (floor($float) == $float) {
            return (string) (int) $float;
        }

        return number_format($float, 2, ',', '.');
    }
}
