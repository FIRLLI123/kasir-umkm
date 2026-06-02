<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\SalesHeader;
use App\Services\SalesService;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    use ApiResponse;

    protected $salesService;

    public function __construct(SalesService $salesService)
    {
        $this->salesService = $salesService;
    }

    public function index(Request $request)
    {
        $query = SalesHeader::with([
            'user',
            'customer.customerGroup',
            'paymentMethod',
        ])->orderByDesc('invoice_date');

        if ($request->filled('start_date')) {
            $query->whereDate('invoice_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('invoice_date', '<=', $request->end_date);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('invoice_no', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('customer_name', 'like', '%'.$search.'%');
                    });
            });
        }

        $sales = $query->paginate((int) $request->get('per_page', 10));

        return $this->successResponse($sales->items(), 'Daftar penjualan berhasil diambil', 200, [
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'discount' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
        ]);

        $sales = $this->salesService->create($validated, $request->user());

        return $this->successResponse($sales, 'Transaksi penjualan berhasil dibuat', 201);
    }

    public function show($id)
    {
        $sales = SalesHeader::with([
            'user',
            'customer.customerGroup',
            'paymentMethod',
            'details.product',
            'voidUser',
        ])->findOrFail($id);

        return $this->successResponse($sales, 'Detail penjualan berhasil diambil');
    }

    public function void(Request $request, $id)
    {
        $validated = $request->validate([
            'void_reason' => 'required|string',
        ]);

        $sales = SalesHeader::findOrFail($id);
        $voidedSales = $this->salesService->void($sales, $request->user(), $validated['void_reason']);

        return $this->successResponse($voidedSales, 'Transaksi berhasil di-void');
    }

    public function receipt($id)
    {
        $sales = SalesHeader::with([
            'user',
            'customer.customerGroup',
            'paymentMethod',
            'details',
        ])->findOrFail($id);

        $settings = AppSetting::where('status', '00')->pluck('setting_value', 'setting_key');

        $receipt = [
            'store_name' => $settings->get('store_name'),
            'store_address' => $settings->get('store_address'),
            'store_phone' => $settings->get('store_phone'),
            'receipt_footer' => $settings->get('receipt_footer'),
            'invoice' => $sales->invoice_no,
            'invoice_date' => optional($sales->invoice_date)->format('Y-m-d H:i:s'),
            'cashier' => optional($sales->user)->name,
            'customer' => optional($sales->customer)->customer_name,
            'payment_method' => optional($sales->paymentMethod)->method_name,
            'items' => $sales->details->map(function ($detail) {
                return [
                    'product_name' => $detail->product_name_snapshot,
                    'qty' => $detail->qty,
                    'selling_price' => $detail->selling_price,
                    'subtotal' => $detail->subtotal,
                ];
            })->values(),
            'subtotal' => $sales->subtotal,
            'discount' => $sales->discount,
            'grand_total' => $sales->grand_total,
            'paid_amount' => $sales->paid_amount,
            'change_amount' => $sales->change_amount,
            'status' => $sales->status,
        ];

        return $this->successResponse($receipt, 'Data struk berhasil diambil');
    }
}
