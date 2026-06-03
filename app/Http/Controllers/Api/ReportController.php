<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SalesHeader;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;

    public function daily(Request $request)
    {
        $date = $request->get('date', now()->toDateString());

        return $this->successResponse(
            $this->summaryBetween($date, $date, $request->user()->company_id),
            'Laporan harian berhasil diambil'
        );
    }

    public function weekly(Request $request)
    {
        $date = Carbon::parse($request->get('date', now()->toDateString()));
        $startDate = $date->copy()->startOfWeek()->toDateString();
        $endDate = $date->copy()->endOfWeek()->toDateString();

        return $this->successResponse(
            $this->summaryBetween($startDate, $endDate, $request->user()->company_id),
            'Laporan mingguan berhasil diambil'
        );
    }

    public function monthly(Request $request)
    {
        $date = Carbon::parse($request->get('date', now()->toDateString()));
        $startDate = $date->copy()->startOfMonth()->toDateString();
        $endDate = $date->copy()->endOfMonth()->toDateString();

        return $this->successResponse(
            $this->summaryBetween($startDate, $endDate, $request->user()->company_id),
            'Laporan bulanan berhasil diambil'
        );
    }

    public function products(Request $request)
    {
        $startDate = $request->get('start_date', now()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());
        $limit = (int) $request->get('limit', 0);

        $query = DB::table('sales_d')
            ->join('sales_h', 'sales_h.id', '=', 'sales_d.sales_h_id')
            ->where('sales_h.company_id', $request->user()->company_id)
            ->where('sales_h.status', '00')
            ->whereBetween(DB::raw('DATE(sales_h.invoice_date)'), [$startDate, $endDate])
            ->select(
                'sales_d.product_id',
                'sales_d.product_name_snapshot as product_name',
                DB::raw('SUM(sales_d.qty) as qty_sold'),
                DB::raw('SUM(sales_d.subtotal) as total_sales'),
                DB::raw('SUM(sales_d.cost_price * sales_d.qty) as total_modal'),
                DB::raw('SUM(sales_d.margin) as total_margin')
            )
            ->groupBy('sales_d.product_id', 'sales_d.product_name_snapshot')
            ->orderByDesc('qty_sold');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $report = $query->get();

        return $this->successResponse($report, 'Laporan produk berhasil diambil');
    }

    public function customers(Request $request)
    {
        $startDate = $request->get('start_date', now()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $report = SalesHeader::query()
            ->join('customers', 'customers.id', '=', 'sales_h.customer_id')
            ->where('sales_h.company_id', $request->user()->company_id)
            ->where('sales_h.status', '00')
            ->whereBetween(DB::raw('DATE(sales_h.invoice_date)'), [$startDate, $endDate])
            ->select(
                'customers.id as customer_id',
                'customers.customer_name',
                DB::raw('COUNT(sales_h.id) as total_transactions'),
                DB::raw('SUM(sales_h.grand_total) as total_sales'),
                DB::raw('SUM(sales_h.total_margin) as total_margin')
            )
            ->groupBy('customers.id', 'customers.customer_name')
            ->orderByDesc('total_sales')
            ->get();

        return $this->successResponse($report, 'Laporan customer berhasil diambil');
    }

    public function paymentMethods(Request $request)
    {
        $startDate = $request->get('start_date', now()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $report = DB::table('payment_methods')
            ->leftJoin('sales_h', function ($join) use ($startDate, $endDate) {
                $join->on('payment_methods.id', '=', 'sales_h.payment_method_id')
                    ->whereBetween(DB::raw('DATE(sales_h.invoice_date)'), [$startDate, $endDate]);
            })
            ->where('payment_methods.company_id', $request->user()->company_id)
            ->select(
                'payment_methods.id as payment_method_id',
                'payment_methods.method_code',
                'payment_methods.method_name',
                DB::raw('COUNT(sales_h.id) as total_transactions'),
                DB::raw('SUM(CASE WHEN sales_h.status = "00" THEN sales_h.grand_total ELSE 0 END) as total_amount'),
                DB::raw('SUM(CASE WHEN sales_h.status = "98" THEN 1 ELSE 0 END) as total_void')
            )
            ->groupBy('payment_methods.id', 'payment_methods.method_code', 'payment_methods.method_name')
            ->orderByDesc('total_amount')
            ->get();

        return $this->successResponse($report, 'Laporan metode pembayaran berhasil diambil');
    }

    protected function summaryBetween($startDate, $endDate, $companyId)
    {
        $summary = SalesHeader::query()
            ->where('company_id', $companyId)
            ->whereBetween(DB::raw('DATE(invoice_date)'), [$startDate, $endDate])
            ->selectRaw('
                COUNT(CASE WHEN status = "00" THEN 1 END) as total_transactions,
                COALESCE(SUM(CASE WHEN status = "00" THEN subtotal ELSE 0 END), 0) as gross_sales,
                COALESCE(SUM(CASE WHEN status = "00" THEN discount ELSE 0 END), 0) as discount,
                COALESCE(SUM(CASE WHEN status = "00" THEN grand_total ELSE 0 END), 0) as net_sales,
                COALESCE(SUM(CASE WHEN status = "00" THEN total_modal ELSE 0 END), 0) as modal,
                COALESCE(SUM(CASE WHEN status = "00" THEN total_margin ELSE 0 END), 0) as margin,
                COALESCE(SUM(CASE WHEN status = "98" THEN 1 ELSE 0 END), 0) as void_transactions
            ')
            ->first();

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_transactions' => (int) $summary->total_transactions,
            'gross_sales' => (float) $summary->gross_sales,
            'discount' => (float) $summary->discount,
            'net_sales' => (float) $summary->net_sales,
            'modal' => (float) $summary->modal,
            'margin' => (float) $summary->margin,
            'void_transactions' => (int) $summary->void_transactions,
        ];
    }
}
