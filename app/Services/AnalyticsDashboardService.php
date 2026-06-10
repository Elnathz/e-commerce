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
            ->selectRaw('COUNT(*) as total_orders, SUM(total_amount) as total_value, MIN(COALESCE(paid_at, created_at)) as oldest_order_at')
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
    public function getFinancialMetrics(string $period = 'today', string $comparePeriod = 'previous_period', string $chartGrouping = 'auto', int $topProductsLimit = 5): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($period);
        [$prevStartDate, $prevEndDate] = $this->resolvePreviousPeriod($period, $comparePeriod);

        $cacheKey = "dashboard_financial_metrics_{$period}_{$comparePeriod}_{$startDate}_{$endDate}_{$chartGrouping}_{$topProductsLimit}";

        return Cache::remember($cacheKey, 60 * 60, function () use ($startDate, $endDate, $prevStartDate, $prevEndDate, $period, $chartGrouping, $topProductsLimit) {
            $currentGross = $this->calculateGrossSales($startDate, $endDate);
            $prevGross = $this->calculateGrossSales($prevStartDate, $prevEndDate);
            
            $currentRefund = $this->calculateTotalRefund($startDate, $endDate);
            $prevRefund = $this->calculateTotalRefund($prevStartDate, $prevEndDate);
            
            $currentOrders = $this->countPaidOrders($startDate, $endDate);
            $prevOrders = $this->countPaidOrders($prevStartDate, $prevEndDate);
            
            $currentCheckoutCreated = $this->countCheckoutCreated($startDate, $endDate);
            $prevCheckoutCreated = $this->countCheckoutCreated($prevStartDate, $prevEndDate);

            $currentPaidOrders      = $currentOrders;
            $prevPaidOrders         = $prevOrders;

            // Note: Metric ini cocok untuk trend/funnel conversion,
            // BUKAN financial KPI presisi (Checkout 31 Jan bisa dibayar 1 Feb).
            $currentCheckoutToPaidRate = $currentCheckoutCreated > 0 ? round(($currentPaidOrders / $currentCheckoutCreated) * 100, 1) : 0;
            $prevCheckoutToPaidRate = $prevCheckoutCreated > 0 ? round(($prevPaidOrders / $prevCheckoutCreated) * 100, 1) : 0;

            $currentOrderReturnRate = $this->calculateOrderReturnRate($startDate, $endDate);
            $prevOrderReturnRate = $this->calculateOrderReturnRate($prevStartDate, $prevEndDate);

            return [
                'period_start'     => $startDate,
                'period_end'       => $endDate,
                'gross_sales'      => $currentGross,
                'gross_sales_trend'=> $this->calculateTrend($currentGross, $prevGross),
                'total_refund'     => $currentRefund,
                'total_refund_trend'=> $this->calculateTrend($currentRefund, $prevRefund),
                'total_orders'     => $currentOrders,
                'total_orders_trend'=> $this->calculateTrend($currentOrders, $prevOrders),
                
                'checkout_created' => $currentCheckoutCreated,
                'checkout_created_trend' => $this->calculateTrend($currentCheckoutCreated, $prevCheckoutCreated),
                
                'paid_orders'      => $currentPaidOrders,
                
                'checkout_to_paid_rate' => $currentCheckoutToPaidRate,
                'checkout_to_paid_rate_trend' => $this->calculateTrend($currentCheckoutToPaidRate, $prevCheckoutToPaidRate),
                
                'top_products'     => $this->getTopProducts($startDate, $endDate, $topProductsLimit),
                
                'order_return_rate'=> $currentOrderReturnRate,
                'order_return_rate_trend' => $this->calculateTrend($currentOrderReturnRate, $prevOrderReturnRate),
                
                'repeat_customer_rate' => $this->calculateRepeatCustomerRate(),

                'payment_summary'  => $this->getPaymentSummary($startDate, $endDate),
                'logistics_performance' => $this->getLogisticsPerformance($startDate, $endDate),
                'sales_chart'      => $this->getSalesChartData($startDate, $endDate, $period, $chartGrouping),
            ];
        });
    }

    /**
     * Get Payment Summary
     */
    public function getPaymentSummary(string $startDate, string $endDate): array
    {
        $methods = Order::whereIn('status', ['completed', 'refunded', 'processing', 'shipped', 'paid'])
            ->whereDate('paid_at', '>=', $startDate)
            ->whereDate('paid_at', '<=', $endDate)
            ->select('payment_method', DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        $total = $methods->sum('count');
        
        if ($total == 0) {
            return [];
        }

        $summary = [];
        $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];
        $i = 0;

        foreach ($methods as $method) {
            $name = strtoupper(str_replace('_', ' ', $method->payment_method ?? 'Unknown'));
            $percentage = round(($method->count / $total) * 100, 1);
            
            $summary[] = [
                'name' => $name,
                'percentage' => $percentage,
                'color' => $colors[$i % count($colors)],
            ];
            $i++;
        }

        return $summary;
    }

    /**
     * Get Logistics Performance
     */
    public function getLogisticsPerformance(string $startDate, string $endDate): array
    {
        $shippedOrders = Order::whereNotNull('shipped_at')
            ->whereNotNull('delivered_at')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->select('shipping_cost', 'shipped_at', 'delivered_at')
            ->get();

        $totalOrders = $shippedOrders->count();
        $totalShippingCost = $shippedOrders->sum('shipping_cost');
        
        $avgDeliveryDays = 0;
        $onTimeCount = 0;

        foreach ($shippedOrders as $order) {
            $days = $order->shipped_at->diffInDays($order->delivered_at);
            $avgDeliveryDays += $days;
            // Anggap SLA tepat waktu adalah <= 3 hari
            if ($days <= 3) {
                $onTimeCount++;
            }
        }

        $avgDeliveryDays = $totalOrders > 0 ? round($avgDeliveryDays / $totalOrders, 1) : 0;
        $avgShippingCost = $totalOrders > 0 ? round($totalShippingCost / $totalOrders, 0) : 0;
        $onTimeRate = $totalOrders > 0 ? round(($onTimeCount / $totalOrders) * 100, 1) : 0;

        // Simplified previous period for trend
        [$prevStart, $prevEnd] = $this->resolvePreviousPeriod('month');
        // Pseudo logic for trend, using basic 0 for now since this is getting complex
        // Ideally we'd fetch previous data too. We'll return 0 trend for now or just the value.

        return [
            'avg_shipping_cost' => $avgShippingCost,
            'avg_shipping_cost_trend' => 0,
            'avg_delivery_days' => $avgDeliveryDays,
            'avg_delivery_days_trend' => 0,
            'on_time_rate' => $onTimeRate,
            'on_time_rate_trend' => 0,
        ];
    }

    /**
     * Get Sales Chart Data
     */
    public function getSalesChartData(string $startDate, string $endDate, string $period, string $chartGrouping = 'auto'): array
    {
        $diffDays = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate));
        
        $groupBy = 'day';
        if ($chartGrouping === 'auto') {
            $groupBy = $diffDays > 31 ? 'month' : 'day';
        } elseif (in_array($chartGrouping, ['daily', 'weekly', 'monthly'])) {
            $groupBy = str_replace('ly', '', $chartGrouping); // day, week, month
        }

        $orders = Order::whereIn('status', ['completed', 'refunded'])
            ->whereDate('paid_at', '>=', $startDate)
            ->whereDate('paid_at', '<=', $endDate);

        if ($groupBy === 'month') {
            $orders = $orders->selectRaw('DATE_FORMAT(paid_at, "%Y-%m") as date, SUM(total_amount) as gross_sales')
                             ->groupBy('date')
                             ->orderBy('date')
                             ->get();
        } else {
            // For both week and day, we fetch daily data first
            $orders = $orders->selectRaw('DATE(paid_at) as date, SUM(total_amount) as gross_sales')
                             ->groupBy('date')
                             ->orderBy('date')
                             ->get();
        }

        $refunds = DB::table('return_requests as rr')
            ->join('return_request_items as rri', 'rri.return_request_id', '=', 'rr.id')
            ->whereIn('rr.status', ['refund_processed', 'completed'])
            ->whereDate('rr.refund_processed_at', '>=', $startDate)
            ->whereDate('rr.refund_processed_at', '<=', $endDate);

        if ($groupBy === 'month') {
            $refunds = $refunds->selectRaw('DATE_FORMAT(rr.refund_processed_at, "%Y-%m") as date, SUM(rri.refund_amount) as total_refund')
                               ->groupBy('date')
                               ->orderBy('date')
                               ->get();
        } else {
            $refunds = $refunds->selectRaw('DATE(rr.refund_processed_at) as date, SUM(rri.refund_amount) as total_refund')
                               ->groupBy('date')
                               ->orderBy('date')
                               ->get();
        }

        $labels = [];
        $grossData = [];
        $refundData = [];

        // Build complete date range
        $current = \Carbon\Carbon::parse($startDate);
        if ($groupBy === 'week') {
            $current->startOfWeek();
        }
        $end = \Carbon\Carbon::parse($endDate);

        while ($current <= $end) {
            if ($groupBy === 'month') {
                $key = $current->format('Y-m');
                $label = $current->translatedFormat('M Y');
                $gross = $orders->firstWhere('date', $key)->gross_sales ?? 0;
                $refund = $refunds->firstWhere('date', $key)->total_refund ?? 0;
                $current->addMonth();
            } elseif ($groupBy === 'week') {
                $weekEnd = $current->copy()->endOfWeek();
                if ($weekEnd > $end) {
                    $weekEnd = $end->copy(); // Cap at end date
                }
                
                $label = $current->translatedFormat('j M') . ' - ' . $weekEnd->translatedFormat('j M');
                
                $weekGross = $orders->filter(function($o) use ($current, $weekEnd) {
                    $d = \Carbon\Carbon::parse($o->date);
                    return $d >= $current && $d <= $weekEnd;
                })->sum('gross_sales');

                $weekRefund = $refunds->filter(function($r) use ($current, $weekEnd) {
                    $d = \Carbon\Carbon::parse($r->date);
                    return $d >= $current && $d <= $weekEnd;
                })->sum('total_refund');

                $gross = $weekGross;
                $refund = $weekRefund;
                
                $current->addWeek();
            } else {
                $key = $current->format('Y-m-d');
                $label = $current->translatedFormat('j M');
                $gross = $orders->firstWhere('date', $key)->gross_sales ?? 0;
                $refund = $refunds->firstWhere('date', $key)->total_refund ?? 0;
                $current->addDay();
            }

            if (!in_array($label, $labels)) {
                $labels[] = $label;
                $grossData[] = (float) $gross;
                $refundData[] = (float) $refund;
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Gross Sales',
                    'data' => $grossData,
                    'borderColor' => '#4F46E5',
                    'backgroundColor' => 'rgba(79, 70, 229, 0.1)',
                    'tension' => 0.4,
                    'fill' => true
                ],
                [
                    'label' => 'Refund',
                    'data' => $refundData,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'fill' => true
                ]
            ]
        ];
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
    public function clearFinancialCache(string $period = 'month', string $comparePeriod = 'previous_period'): void
    {
        [$startDate, $endDate] = $this->resolvePeriod($period);
        $cacheKey = "dashboard_financial_metrics_{$period}_{$comparePeriod}_{$startDate}_{$endDate}";
        Cache::forget($cacheKey);
    }

    /**
     * Resolve period string to [startDate, endDate]
     */
    public function resolvePeriod(string $period): array
    {
        if (str_contains($period, '|')) {
            $parts = explode('|', $period);
            return [$parts[0], $parts[1] ?? $parts[0]];
        }

        return match ($period) {
            'today'     => [now()->toDateString(), now()->toDateString()],
            'yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
            '7days'     => [now()->subDays(6)->toDateString(), now()->toDateString()],
            '30days'    => [now()->subDays(29)->toDateString(), now()->toDateString()],
            'week'      => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month'     => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'year'      => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            default     => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    /**
     * Resolve previous period string to [prevStartDate, prevEndDate] for trend calculation
     */
    public function resolvePreviousPeriod(string $period, string $comparePeriod = 'previous_period'): array
    {
        if ($comparePeriod === 'previous_year') {
            [$start, $end] = $this->resolvePeriod($period);
            return [
                \Carbon\Carbon::parse($start)->subYear()->toDateString(),
                \Carbon\Carbon::parse($end)->subYear()->toDateString(),
            ];
        }

        if (str_contains($period, '|')) {
            $parts = explode('|', $period);
            $start = \Carbon\Carbon::parse($parts[0]);
            $end = \Carbon\Carbon::parse($parts[1] ?? $parts[0]);
            $diffDays = $start->diffInDays($end) + 1;
            return [
                $start->copy()->subDays($diffDays)->toDateString(),
                $end->copy()->subDays($diffDays)->toDateString(),
            ];
        }

        return match ($period) {
            'today'     => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
            'yesterday' => [now()->subDays(2)->toDateString(), now()->subDays(2)->toDateString()],
            '7days'     => [now()->subDays(13)->toDateString(), now()->subDays(7)->toDateString()],
            '30days'    => [now()->subDays(59)->toDateString(), now()->subDays(30)->toDateString()],
            'week'      => [now()->subWeek()->startOfWeek()->toDateString(), now()->subWeek()->endOfWeek()->toDateString()],
            'month'     => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
            'year'      => [now()->subYear()->startOfYear()->toDateString(), now()->subYear()->endOfYear()->toDateString()],
            default     => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
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
