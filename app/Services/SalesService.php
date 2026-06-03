<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SalesDetail;
use App\Models\SalesHeader;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class SalesService
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function create(array $payload, User $user)
    {
        return DB::transaction(function () use ($payload, $user) {
            $customer = Customer::with('customerGroup')->findOrFail($payload['customer_id']);
            $paymentMethod = PaymentMethod::findOrFail($payload['payment_method_id']);

            if ($customer->status !== '00') {
                $this->fail('Customer tidak aktif');
            }

            if ($paymentMethod->status !== '00') {
                $this->fail('Metode pembayaran tidak aktif');
            }

            $items = [];
            $subtotal = 0;
            $totalModal = 0;
            $totalMargin = 0;

            foreach ($payload['items'] as $item) {
                $product = Product::with(['prices' => function ($query) use ($customer) {
                    $query->where('customer_group_id', $customer->customer_group_id)
                        ->where('status', '00');
                }])->findOrFail($item['product_id']);

                if ($product->status !== '00') {
                    $this->fail('Ada produk yang tidak aktif', ['product_id' => $product->id]);
                }

                $price = $product->prices->first();

                if (! $price) {
                    $this->fail(
                        'Harga produk untuk golongan customer tidak ditemukan',
                        ['product_id' => $product->id]
                    );
                }

                $lineSubtotal = $item['qty'] * $price->selling_price;
                $lineModal = $item['qty'] * $product->cost_price;
                $lineMargin = $lineSubtotal - $lineModal;

                $items[] = [
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->product_name,
                    'qty' => $item['qty'],
                    'cost_price' => $product->cost_price,
                    'selling_price' => $price->selling_price,
                    'subtotal' => $lineSubtotal,
                    'margin' => $lineMargin,
                    'status' => '00',
                ];

                $subtotal += $lineSubtotal;
                $totalModal += $lineModal;
                $totalMargin += $lineMargin;
            }

            $discount = isset($payload['discount']) ? $payload['discount'] : 0;
            $grandTotal = $subtotal - $discount;
            $paidAmount = $payload['paid_amount'];

            if ($paidAmount < $grandTotal) {
                $this->fail('Jumlah bayar kurang dari grand total');
            }

            $sales = SalesHeader::create([
                'company_id' => $user->company_id,
                'invoice_no' => $this->generateInvoiceNumber(),
                'invoice_date' => now(),
                'user_id' => $user->id,
                'customer_id' => $customer->id,
                'customer_group_id' => $customer->customer_group_id,
                'payment_method_id' => $paymentMethod->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'grand_total' => $grandTotal,
                'total_modal' => $totalModal,
                'total_margin' => $totalMargin,
                'paid_amount' => $paidAmount,
                'change_amount' => $paidAmount - $grandTotal,
                'status' => '00',
            ]);

            foreach ($items as $item) {
                $item['company_id'] = $user->company_id;
                $item['sales_h_id'] = $sales->id;
                SalesDetail::create($item);

                $product = Product::findOrFail($item['product_id']);
                $this->stockService->deductForSale(
                    $product,
                    $item['qty'],
                    $sales->id,
                    $user,
                    'Pengurangan stok untuk invoice '.$sales->invoice_no
                );
            }

            return $sales->load([
                'user',
                'customer.customerGroup',
                'paymentMethod',
                'details.product',
            ]);
        });
    }

    public function void(SalesHeader $sales, User $user, $reason)
    {
        return DB::transaction(function () use ($sales, $user, $reason) {
            $sales->load('details');

            if ($sales->status !== '00') {
                $this->fail('Hanya transaksi sukses yang bisa di-void');
            }

            $sales->update([
                'status' => '98',
                'void_reason' => $reason,
                'void_by' => $user->id,
                'void_at' => now(),
            ]);

            SalesDetail::where('sales_h_id', $sales->id)->update(['status' => '98']);

            foreach ($sales->details as $detail) {
                $product = Product::findOrFail($detail->product_id);
                $this->stockService->restoreFromVoid(
                    $product,
                    $detail->qty,
                    $sales->id,
                    $user,
                    'Pengembalian stok dari void invoice '.$sales->invoice_no
                );
            }

            return $sales->fresh()->load([
                'user',
                'customer.customerGroup',
                'paymentMethod',
                'details.product',
                'voidUser',
            ]);
        });
    }

    protected function generateInvoiceNumber()
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';
        $lastInvoice = SalesHeader::where('invoice_no', 'like', $prefix.'%')
            ->where('company_id', auth()->user()->company_id)
            ->orderByDesc('id')
            ->value('invoice_no');

        $sequence = 1;

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice, -4);
            $sequence = $lastNumber + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
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
