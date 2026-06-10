<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsDashboardService
{
    /**
     * Low stock threshold — configurable via env.
     * Roadmap Fase 2: per-variant threshold column.
     */
    protected int $lowStockThreshold;

    public function __construct()
    {
        $this->lowStockThreshold = (int) env('LOW_STOCK_THRESHOLD', 5);
    }

    /**
     * Get Operational Metrics (Real-time, not cached)
     */
    public function getOperationalMetrics(): array
    {
        return [
            'need_fulfillment'    => Order::where('status', 'paid')->count(),
            'in_processing'       => Order::where('status', 'processing')->count(),
            'low_stock_count'     => $this->countLowStockProducts(),
            'awaiting_approval'   => ReturnRequest::where('status', 'submitted')->count(),
            'awaiting_inspection' => ReturnRequest::where('status', 'received')->count(),
            'sla_breaches'        => $this->getSLABreaches(),
            'financial_exposure'  => $this->getFinancialExposure(),
            'pending_shipment'    => $this->getPendingShipmentStats(),
            'priority_actions'    => $this->getPriorityActionsFeed(),
        ];
    }

    /**
     * Get Priority Actions Feed (Real-time task list)
     */
    public function getPriorityActionsFeed(): array
    {
        $feed = [];

        // 1. Pesanan yang butuh diproses (Paling Lama)
        $orders = Order::where('status', 'paid')
            ->orderBy('paid_at', 'asc')
            ->limit(5)
            ->get();
        
        foreach ($orders as $order) {
            $feed[] = [
                'id' => $order->id,
                'type' => 'order_paid',
                'title' => 'Pesanan Baru: ' . $order->order_number,
                'message' => 'Menunggu untuk diproses dan dikemas.',
                'action_url' => route('admin.orders.show', $order->id),
                'timestamp' => $order->paid_at,
                'priority' => 'high'
            ];
        }

        // 2. Retur yang butuh persetujuan
        $returns = ReturnRequest::where('status', 'submitted')
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get();
            
        foreach ($returns as $ret) {
            $feed[] = [
                'id' => $ret->id,
                'type' => 'return_submitted',
                'title' => 'Pengajuan Retur: ' . $ret->return_number,
                'message' => 'Menunggu persetujuan admin.',
                'action_url' => route('admin.returns.show', $ret->id),
                'timestamp' => $ret->created_at,
                'priority' => 'critical'
            ];
        }

        // 3. Retur yang butuh inspeksi
        $returnsToInspect = ReturnRequest::where('status', 'received')
            ->orderBy('return_received_at', 'asc')
            ->limit(5)
            ->get();
            
        foreach ($returnsToInspect as $ret) {
            $feed[] = [
                'id' => $ret->id,
                'type' => 'return_received',
                'title' => 'Inspeksi Retur: ' . $ret->return_number,
                'message' => 'Barang retur sudah tiba di gudang dan butuh inspeksi.',
                'action_url' => route('admin.returns.show', $ret->id),
                'timestamp' => $ret->return_received_at,
                'priority' => 'critical'
            ];
        }

        // Urutkan berdasarkan waktu paling lama (paling mendesak)
        usort($feed, function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        // Ambil 10 teratas
        return array_slice($feed, 0, 10);
    }

    /**
     * Get SLA Breaches (Real-time)
     */
    public function getSLABreaches(): array
    {
        $uninspectedReturns = \App\Models\ReturnRequest::where('status', 'received')
            ->where('return_received_at', '<', now()->subHours(24))
            ->count();

        $unrefundedReturns = \App\Models\ReturnRequest::where('status', 'inspected')
            ->where('inspection_result', 'passed')
            ->where('inspected_at', '<', now()->subHours(24))
            ->count();

        return [
            'uninspected_returns' => $uninspectedReturns,
            'unrefunded_returns' => $unrefundedReturns,
            'total_breaches' => $uninspectedReturns + $unrefundedReturns,
        ];
    }

    /**
     * Get Financial Exposure (Revenue at risk)
     */
    public function getFinancialExposure(): float
    {
        return (float) DB::table('promotion_usages')
            ->where('status', 'reserved')
            ->sum('discount_applied');
    }

    /**
     * Get Pending Shipment Stats
     */
    public function getPendingShipmentStats(): array
    {
        $stats = Order::where('status', 'processing')
            ->selectRaw('COUNT(*) as total_orders, SUM(total_amount) as total_value, MIN(processing_at) as oldest_order_at')
            ->first();

        return [
            'count' => $stats->total_orders ?? 0,
            'value' => $stats->total_value ?? 0,
            'oldest_at' => $stats->oldest_order_at,
        ];
    }

    /**
     * Get Financial Metrics (Cached)
     */
    public function getFinancialMetrics(string $period = 'today'): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($period);
        [$prevStartDate, $prevEndDate] = $this->resolvePreviousPeriod($period);

        $cacheKey = "dashboard_financial_metrics_{$period}_{$startDate}_{$endDate}";

        return Cache::remember($cacheKey, 300, function () use ($startDate, $endDate, $prevStartDate, $prevEndDate) {
            $currentGross = $this->calculateGrossSales($startDate, $endDate);
            $prevGross = $this->calculateGrossSales($prevStartDate, $prevEndDate);
            
            $currentRefund = $this->calculateTotalRefund($startDate, $endDate);
            $prevRefund = $this->calculateTotalRefund($prevStartDate, $prevEndDate);
            
            $currentOrders = $this->countPaidOrders($startDate, $endDate);
            $prevOrders = $this->countPaidOrders($prevStartDate, $prevEndDate);
            
            $checkoutCreated = $this->countCheckoutCreated($startDate, $endDate);
            $paidOrders      = $currentOrders;

            // Note: Metric ini cocok untuk trend/funnel conversion,
            // BUKAN financial KPI presisi (Checkout 31 Jan bisa dibayar 1 Feb).
            $checkoutToPaidRate = $checkoutCreated > 0 ? round(($paidOrders / $checkoutCreated) * 100, 1) : 0;

            return [
                'gross_sales'      => $currentGross,
                'gross_sales_trend'=> $this->calculateTrend($currentGross, $prevGross),
                'total_refund'     => $currentRefund,
                'total_refund_trend'=> $this->calculateTrend($currentRefund, $prevRefund),
                'total_orders'     => $currentOrders,
                'total_orders_trend'=> $this->calculateTrend($currentOrders, $prevOrders),
                'checkout_created' => $checkoutCreated,
                'paid_orders'      => $paidOrders,
                'checkout_to_paid_rate' => $checkoutToPaidRate,
                'top_products'     => $this->getTopProducts($startDate, $endDate, 3), // Only top 3 for visual
                'order_return_rate'=> $this->calculateOrderReturnRate($startDate, $endDate),
                'repeat_customer_rate' => $this->calculateRepeatCustomerRate(),
            ];
        });
    }

    /**
     * Legacy getter if needed
     */
    public function getDashboardMetrics(string $period = 'today'): array
    {
        return array_merge(
            $this->getFinancialMetrics($period),
            $this->getOperationalMetrics(),
            ['period' => $period]
        );
    }

    /**
     * Gross Sales: based on order paid_at date (not created_at).
     * Includes: completed + refunded status orders.
     * Note: Metric ini menunjukkan tren, bukan angka finansial presisi
     *       (checkout 31 Jan bisa dibayar 1 Feb).
     */
    public function calculateGrossSales(string $startDate, string $endDate): float
    {
        return (float) Order::whereIn('status', ['completed', 'refunded'])
            ->whereDate('paid_at', '>=', $startDate)
            ->whereDate('paid_at', '<=', $endDate)
            ->sum('total_amount');
    }

    /**
     * Total Refund: based on refund_processed_at (independent period from gross sales).
     * Source: return_request_items.refund_amount — handles partial refunds correctly.
     */
    public function calculateTotalRefund(string $startDate, string $endDate): float
    {
        return (float) DB::table('return_requests as rr')
            ->join('return_request_items as rri', 'rri.return_request_id', '=', 'rr.id')
            ->whereIn('rr.status', ['refund_processed', 'completed'])
            ->whereDate('rr.refund_processed_at', '>=', $startDate)
            ->whereDate('rr.refund_processed_at', '<=', $endDate)
            ->sum('rri.refund_amount');
    }

    /** Total orders (paid or beyond — exclude cancelled/pending) */
    public function countOrders(string $startDate, string $endDate): int
    {
        return Order::whereNotIn('status', ['pending', 'cancelled'])
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->count();
    }

    /** All checkout attempts regardless of status */
    public function countCheckoutCreated(string $startDate, string $endDate): int
    {
        return Order::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->count();
    }

    /** Orders that were successfully paid */
    public function countPaidOrders(string $startDate, string $endDate): int
    {
        return Order::whereNotIn('status', ['pending', 'cancelled'])
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->count();
    }

    /** Top 10 products by quantity sold */
    public function getTopProducts(string $startDate, string $endDate, int $limit = 10): array
    {
        return DB::table('order_items as oi')
            ->join('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereIn('o.status', ['completed', 'refunded'])
            ->whereDate('o.paid_at', '>=', $startDate)
            ->whereDate('o.paid_at', '<=', $endDate)
            ->select(
                'p.id',
                'p.name',
                DB::raw('SUM(oi.quantity) as total_sold'),
                DB::raw('SUM(oi.subtotal) as total_revenue')
            )
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Order Return Rate = returned_orders / total_completed_orders * 100
     * Counted per-order, not per-item. Partial return = 1 return.
     */
    public function calculateOrderReturnRate(string $startDate, string $endDate): float
    {
        $totalCompleted = Order::whereIn('status', ['completed', 'refunded'])
            ->whereDate('paid_at', '>=', $startDate)
            ->whereDate('paid_at', '<=', $endDate)
            ->count();

        if ($totalCompleted === 0) return 0.0;

        $returnedOrders = DB::table('orders as o')
            ->join('return_requests as rr', 'rr.order_id', '=', 'o.id')
            ->whereIn('o.status', ['completed', 'refunded'])
            ->whereIn('rr.status', ['approved', 'returned', 'received', 'refund_processed', 'completed'])
            ->whereDate('o.paid_at', '>=', $startDate)
            ->whereDate('o.paid_at', '<=', $endDate)
            ->distinct('o.id')
            ->count('o.id');

        return round(($returnedOrders / $totalCompleted) * 100, 2);
    }

    /**
     * Repeat Customer Rate = customers with >1 completed orders / total customers
     */
    public function calculateRepeatCustomerRate(): float
    {
        $totalCustomers = Order::whereIn('status', ['completed', 'refunded'])
            ->distinct('user_id')
            ->count('user_id');

        if ($totalCustomers === 0) return 0.0;

        $repeatCustomers = DB::table('orders')
            ->whereIn('status', ['completed', 'refunded'])
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return round(($repeatCustomers / $totalCustomers) * 100, 2);
    }

    /** Products with available stock <= threshold */
    public function countLowStockProducts(): int
    {
        return ProductVariant::where('is_active', true)
            ->whereRaw('(stock - reserved_stock) <= ?', [$this->lowStockThreshold])
            ->distinct('product_id')
            ->count('product_id');
    }

    /** Low stock product details for drill-down */
    public function getLowStockProducts(int $limit = 20): array
    {
        return DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('pv.is_active', true)
            ->whereRaw('(pv.stock - pv.reserved_stock) <= ?', [$this->lowStockThreshold])
            ->select(
                'p.id',
                'p.name',
                'p.slug',
                'pv.id as variant_id',
                'pv.name as variant_name',
                'pv.sku',
                DB::raw('(pv.stock - pv.reserved_stock) as available_stock')
            )
            ->orderBy('available_stock')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Clear Financial Cache
     */
    public function clearFinancialCache(string $period = 'month'): void
    {
        [$startDate, $endDate] = $this->resolvePeriod($period);
        $cacheKey = "dashboard_financial_metrics_{$period}_{$startDate}_{$endDate}";
        Cache::forget($cacheKey);
    }

    /**
     * Resolve period string to [startDate, endDate]
     */
    protected function resolvePeriod(string $period): array
    {
        return match ($period) {
            'today'  => [now()->toDateString(), now()->toDateString()],
            'week'   => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month'  => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'year'   => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            default  => [now()->toDateString(), now()->toDateString()],
        };
    }

    /**
     * Resolve previous period string to [prevStartDate, prevEndDate] for trend calculation
     */
    protected function resolvePreviousPeriod(string $period): array
    {
        return match ($period) {
            'today'  => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
            'week'   => [now()->subWeek()->startOfWeek()->toDateString(), now()->subWeek()->endOfWeek()->toDateString()],
            'month'  => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
            'year'   => [now()->subYear()->startOfYear()->toDateString(), now()->subYear()->endOfYear()->toDateString()],
            default  => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
        };
    }

    /**
     * Calculate percentage trend
     */
    protected function calculateTrend(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
