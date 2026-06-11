<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ProductVariant;
use App\Models\Promotion;
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
        $feed = $this->buildPriorityActionsFeed();
        $slaBreaches = $this->getSLABreaches();
        $lowStockCount = $this->countLowStockProducts();

        return [
            'need_fulfillment'    => Order::where('status', 'paid')->count(),
            'in_processing'       => Order::where('status', 'processing')->count(),
            'low_stock_count'     => $lowStockCount,
            'awaiting_approval'   => ReturnRequest::where('status', 'submitted')->count(),
            'awaiting_inspection' => ReturnRequest::where('status', 'received')->count(),
            'sla_breaches'        => $slaBreaches,
            'revenue_at_risk'     => $this->getRevenueAtRisk(),
            'pending_shipment'    => $this->getPendingShipmentValue(),
            'oldest_order'        => $this->getOldestUnprocessedOrder(),
            'priority_actions'    => array_slice($feed, 0, 10),
            'today_focus'         => $this->getTodayFocusSummary($feed, $slaBreaches, $lowStockCount),
            'vouchers_alert'      => $this->getVouchersNeedingAttention(),
        ];
    }

    /**
     * Get Priority Actions Feed (Real-time task list)
     *
     * Ranking mengikuti kontrak prioritas §2.5 (tdd_changes_tracker.md #42, #46):
     * priority_score = f(severity_tier, category_weight, age) — severity DOMINAN,
     * category_weight & age hanya tiebreaker. Memperbaiki deviasi #42 (sort
     * sebelumnya murni timestamp ASC, mengabaikan field `priority`).
     */
    public function getPriorityActionsFeed(): array
    {
        return array_slice($this->buildPriorityActionsFeed(), 0, 10);
    }

    /**
     * Bangun seluruh priority actions feed (belum dipotong ke 10), terurut
     * priority_score DESC. Dipakai oleh getPriorityActionsFeed() (slice 10) DAN
     * getTodayFocusSummary() (§2.7, roll-up) agar keduanya berasal dari sumber
     * yang identik — syarat integritas §2.7-C.
     */
    private function buildPriorityActionsFeed(): array
    {
        $feed = [];

        // 1. Pesanan yang butuh diproses (Paling Lama)
        $orders = Order::where('status', 'paid')
            ->orderBy('paid_at', 'asc')
            ->limit(5)
            ->get();

        foreach ($orders as $order) {
            $feed[] = $this->buildPriorityActionItem(
                id: $order->id,
                type: 'order_paid',
                title: 'Pesanan Baru: ' . $order->order_number,
                message: 'Menunggu untuk diproses dan dikemas.',
                actionUrl: route('admin.orders.show', $order->id),
                timestamp: $order->paid_at,
                priority: 'high',
                categoryWeight: 2,
                breachThresholdHours: 24, // selaras order_paid_overdue (getSLABreaches)
            );
        }

        // 2. Retur yang butuh persetujuan
        $returns = ReturnRequest::where('status', 'submitted')
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get();

        foreach ($returns as $ret) {
            $feed[] = $this->buildPriorityActionItem(
                id: $ret->id,
                type: 'return_submitted',
                title: 'Pengajuan Retur: ' . $ret->return_number,
                message: 'Menunggu persetujuan admin.',
                actionUrl: route('admin.returns.show', $ret->id),
                timestamp: $ret->created_at,
                priority: 'critical',
                categoryWeight: 1,
                breachThresholdHours: 24, // selaras return_submitted_overdue (getSLABreaches)
            );
        }

        // 3. Retur yang butuh inspeksi
        $returnsToInspect = ReturnRequest::where('status', 'received')
            ->orderBy('return_received_at', 'asc')
            ->limit(5)
            ->get();

        foreach ($returnsToInspect as $ret) {
            $feed[] = $this->buildPriorityActionItem(
                id: $ret->id,
                type: 'return_received',
                title: 'Inspeksi Retur: ' . $ret->return_number,
                message: 'Barang retur sudah tiba di gudang dan butuh inspeksi.',
                actionUrl: route('admin.returns.show', $ret->id),
                timestamp: $ret->return_received_at,
                priority: 'critical',
                categoryWeight: 3,
                breachThresholdHours: 48, // selaras return_received_overdue (getSLABreaches)
            );
        }

        // Urutkan berdasarkan priority_score DESC (severity dominan, lalu category_weight, lalu age)
        usort($feed, function($a, $b) {
            return $b['priority_score'] <=> $a['priority_score'];
        });

        return $feed;
    }

    /**
     * Today Focus Summary roll-up (§2.7) — "hari ini saya harus kerjakan apa?"
     *
     * Sumber data (TANPA backend baru, §2.7): roll-up dari $feed (priority_score §2.5,
     * full sebelum slice 10), `sla_breaches` (sudah dihitung getSLABreaches()), dan
     * `low_stock_count` (sudah dihitung countLowStockProducts()) — semua sudah jadi
     * bagian getOperationalMetrics(), tidak ada query baru.
     *
     * Integritas (§2.7-C): order_paid_overdue & return_*_overdue dari sla_breaches
     * memakai threshold YANG SAMA dengan severity='critical' di buildPriorityActionItem()
     * (#49: 24j/24j/48j) — sehingga count di sini selaras dengan item severity=critical
     * pada $feed yang dipakai Priority Feed untuk drill-down.
     *
     * §2.7-B: 'is_all_clear' = true HANYA jika TIDAK ADA item critical/warning sama
     * sekali (order_paid, return SLA, stok, maupun item 'warning' lain di $feed) —
     * inilah SSOT success-state, PriorityFeed tidak boleh menduplikasi klaim "aman".
     */
    private function getTodayFocusSummary(array $feed, array $slaBreaches, int $lowStockCount): array
    {
        $returnSlaOverdue = $slaBreaches['return_submitted_overdue'] + $slaBreaches['return_received_overdue'];
        $approachingCount = count(array_filter($feed, fn ($item) => $item['severity'] === 'warning'));

        $items = [];

        if ($slaBreaches['order_paid_overdue'] > 0) {
            $items[] = [
                'key' => 'order_paid_overdue',
                'severity' => 'critical',
                'count' => $slaBreaches['order_paid_overdue'],
                'label' => $slaBreaches['order_paid_overdue'] . ' Order terlambat diproses',
                'filter_types' => ['order_paid'],
                'filter_severity' => 'critical',
            ];
        }

        if ($returnSlaOverdue > 0) {
            $items[] = [
                'key' => 'return_sla_overdue',
                'severity' => 'critical',
                'count' => $returnSlaOverdue,
                'label' => $returnSlaOverdue . ' Retur melewati SLA',
                'filter_types' => ['return_submitted', 'return_received'],
                'filter_severity' => 'critical',
            ];
        }

        if ($lowStockCount > 0) {
            // Threshold ">4 = critical" mencerminkan stockAlertTier (PrioritySummary.vue),
            // bukan ambang baru — lihat tracker #51.
            $items[] = [
                'key' => 'low_stock',
                'severity' => $lowStockCount > 4 ? 'critical' : 'warning',
                'count' => $lowStockCount,
                'label' => $lowStockCount . ' Produk stok kritis',
                'filter_types' => null,
                'filter_severity' => null,
            ];
        }

        if ($approachingCount > 0) {
            $items[] = [
                'key' => 'approaching_sla',
                'severity' => 'warning',
                'count' => $approachingCount,
                'label' => $approachingCount . ' item mendekati batas SLA',
                'filter_types' => ['order_paid', 'return_submitted', 'return_received'],
                'filter_severity' => 'warning',
            ];
        }

        return [
            'items' => $items,
            'is_all_clear' => empty($items),
        ];
    }

    /**
     * Bangun satu item Priority Actions Feed dengan severity & priority_score (§2.5/#46).
     *
     * - severity_tier: critical(3) jika age >= threshold (selaras getSLABreaches),
     *   warning(2) jika age >= 75% threshold, info(1) selainnya.
     * - category_weight: tiebreaker antar item severity sama, urutan dari
     *   implementation_plan.md Komponen 3 (return_received > order_paid > return_submitted).
     * - age (jam, dibatasi 999) adalah tiebreaker terakhir.
     */
    private function buildPriorityActionItem(
        int $id,
        string $type,
        string $title,
        string $message,
        string $actionUrl,
        $timestamp,
        string $priority,
        int $categoryWeight,
        int $breachThresholdHours,
    ): array {
        $ageHours = $timestamp ? (int) round(now()->diffInHours($timestamp, true)) : 0;

        if ($ageHours >= $breachThresholdHours) {
            $severity = 'critical';
            $severityTier = 3;
        } elseif ($ageHours >= $breachThresholdHours * 0.75) {
            $severity = 'warning';
            $severityTier = 2;
        } else {
            $severity = 'info';
            $severityTier = 1;
        }

        return [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'timestamp' => $timestamp,
            'priority' => $priority,
            'severity' => $severity,
            'priority_score' => ($severityTier * 1_000_000) + ($categoryWeight * 1_000) + min($ageHours, 999),
        ];
    }

    /**
     * Get SLA Breaches (Real-time)
     */
    public function getSLABreaches(): array
    {
        $orderPaidOverdue = Order::where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '<', now()->subHours(24))
            ->count();

        $orderProcessingOverdue = Order::where('status', 'processing')
            ->whereNotNull('processing_at')
            ->where('processing_at', '<', now()->subHours(48))
            ->count();

        $returnSubmittedOverdue = ReturnRequest::where('status', 'submitted')
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        $returnReceivedOverdue = ReturnRequest::where('status', 'received')
            ->whereNotNull('return_received_at')
            ->where('return_received_at', '<', now()->subHours(48))
            ->count();

        $returnRefundOverdue = ReturnRequest::where('status', 'inspected')
            ->whereNotNull('inspected_at')
            ->where('inspected_at', '<', now()->subHours(72))
            ->whereNull('refund_processed_at')
            ->count();

        return [
            'order_paid_overdue' => $orderPaidOverdue,
            'order_processing_overdue' => $orderProcessingOverdue,
            'return_submitted_overdue' => $returnSubmittedOverdue,
            'return_received_overdue' => $returnReceivedOverdue,
            'return_refund_overdue' => $returnRefundOverdue,
            'total_breaches' => $orderPaidOverdue + $orderProcessingOverdue + $returnSubmittedOverdue + $returnReceivedOverdue + $returnRefundOverdue,
        ];
    }

    /**
     * Get Revenue At Risk (3 komponen terpisah)
     */
    public function getRevenueAtRisk(): array
    {
        $pendingPayment = Order::where('status', 'pending')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count')
            ->first();

        $pendingRefund = DB::table('return_requests as rr')
            ->join('return_request_items as rri', 'rri.return_request_id', '=', 'rr.id')
            ->whereIn('rr.status', ['approved', 'received', 'inspected'])
            ->whereNull('rr.refund_processed_at')
            ->selectRaw('COALESCE(SUM(rri.refund_amount), 0) as total')
            ->value('total');

        $reservedVoucher = DB::table('promotion_usages')
            ->where('status', 'reserved')
            ->selectRaw('COALESCE(SUM(discount_applied), 0) as total')
            ->value('total');

        return [
            'pending_payment_amount'  => (float) $pendingPayment->total,
            'pending_payment_count'   => (int)   $pendingPayment->count,
            'pending_refund_amount'   => (float) $pendingRefund,
            'reserved_voucher_amount' => (float) $reservedVoucher,
        ];
    }

    /**
     * Get Pending Shipment Value
     */
    public function getPendingShipmentValue(): array
    {
        $result = Order::where('status', 'processing')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_value, COUNT(*) as order_count')
            ->first();

        return [
            'total_value'  => (float) $result->total_value,
            'order_count'  => (int)   $result->order_count,
        ];
    }

    /**
     * Get Oldest Unprocessed Order
     */
    public function getOldestUnprocessedOrder(): ?array
    {
        $baseQuery = Order::where('status', 'paid');
        $totalWaiting = $baseQuery->count();

        if ($totalWaiting === 0) return null;

        $order = $baseQuery->whereNotNull('paid_at')
            ->orderBy('paid_at', 'asc')
            ->with('user:id,name')
            ->select(['id', 'order_number', 'paid_at', 'total_amount', 'user_id'])
            ->first();

        if (!$order) return null;

        return [
            'id'            => $order->id,
            'order_number'  => $order->order_number,
            'total_amount'  => $order->total_amount,
            'paid_at'       => $order->paid_at,
            'waiting_hours' => round(now()->diffInMinutes($order->paid_at) / 60, 1),
            'customer_name' => $order->user->name ?? 'Unknown',
            'total_waiting' => $totalWaiting,
        ];
    }

    /**
     * Get Vouchers Needing Attention
     */
    public function getVouchersNeedingAttention(): array
    {
        return Promotion::where('is_active', true)
            ->whereNotNull('max_usage')
            ->where('max_usage', '>', 0)
            ->get()
            ->map(function ($promo) {
                $remaining = max(0, $promo->max_usage - $promo->used_count);
                $pct = ($remaining / $promo->max_usage) * 100;

                // 2-tier: critical = sisa absolut <= 5, warning = sisa pct <= 10%
                if ($remaining <= 5) {
                    $severity = 'critical';
                } elseif ($pct <= 10.0) {
                    $severity = 'warning';
                } else {
                    return null;
                }

                return [
                    'id'        => $promo->id,
                    'name'      => $promo->name,
                    'code'      => $promo->code,
                    'remaining' => $remaining,
                    'pct_used'  => round(100 - $pct, 1),
                    'severity'  => $severity,
                ];
            })
            ->filter()
            ->sortBy(fn($v) => $v['severity'] === 'critical' ? 0 : 1)
            ->values()
            ->toArray();
    }

    /**
     * Get Financial Metrics (Cached)
     */
    public function getFinancialMetrics(string $period = 'today', string $comparePeriod = 'previous_period', string $chartGrouping = 'auto', int $topProductsLimit = 5): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($period);
        [$prevStartDate, $prevEndDate] = $this->resolvePreviousPeriod($period, $comparePeriod);

        $cacheKey = "dashboard_financial_metrics_{$period}_{$comparePeriod}_{$startDate}_{$endDate}_{$chartGrouping}_{$topProductsLimit}";

        return Cache::remember($cacheKey, 60 * 60, function () use ($startDate, $endDate, $prevStartDate, $prevEndDate, $period, $comparePeriod, $chartGrouping, $topProductsLimit) {
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
                // §1a / KPI Context: label periode pembanding, dirender di sebelah delta tren.
                'comparison_label' => $this->resolveComparisonLabel($period, $comparePeriod, $prevStartDate, $prevEndDate),
                'gross_sales'      => $currentGross,
                'gross_sales_trend'=> $this->calculateTrend($currentGross, $prevGross, 'currency'),
                'total_refund'     => $currentRefund,
                'total_refund_trend'=> $this->calculateTrend($currentRefund, $prevRefund, 'currency'),
                'total_orders'     => $currentOrders,
                'total_orders_trend'=> $this->calculateTrend($currentOrders, $prevOrders, 'count'),

                'checkout_created' => $currentCheckoutCreated,
                'checkout_created_trend' => $this->calculateTrend($currentCheckoutCreated, $prevCheckoutCreated, 'count'),

                'paid_orders'      => $currentPaidOrders,

                'checkout_to_paid_rate' => $currentCheckoutToPaidRate,
                'checkout_to_paid_rate_trend' => $this->calculateTrend($currentCheckoutToPaidRate, $prevCheckoutToPaidRate, 'rate'),

                'top_products'     => $this->getTopProducts($startDate, $endDate, $topProductsLimit),

                'order_return_rate'=> $currentOrderReturnRate,
                'order_return_rate_trend' => $this->calculateTrend($currentOrderReturnRate, $prevOrderReturnRate, 'rate'),

                'repeat_customer_rate' => $this->calculateRepeatCustomerRate(),

                'payment_summary'  => $this->getPaymentSummary($startDate, $endDate),
                'logistics_performance' => $this->getLogisticsPerformance($startDate, $endDate, $prevStartDate, $prevEndDate),
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
    public function getLogisticsPerformance(string $startDate, string $endDate, ?string $prevStartDate = null, ?string $prevEndDate = null): array
    {
        $current = $this->calculateLogisticsStats($startDate, $endDate);

        $previous = ($prevStartDate && $prevEndDate)
            ? $this->calculateLogisticsStats($prevStartDate, $prevEndDate)
            : ['avg_shipping_cost' => 0, 'avg_delivery_days' => 0];

        return [
            'avg_shipping_cost' => $current['avg_shipping_cost'],
            'avg_shipping_cost_trend' => $this->calculateTrend($current['avg_shipping_cost'], $previous['avg_shipping_cost'], 'currency'),
            'avg_delivery_days' => $current['avg_delivery_days'],
            'avg_delivery_days_trend' => $this->calculateTrend($current['avg_delivery_days'], $previous['avg_delivery_days'], 'count'),
        ];
    }

    /**
     * Calculate average shipping cost & delivery days for a date range
     */
    protected function calculateLogisticsStats(string $startDate, string $endDate): array
    {
        $shippedOrders = Order::whereNotNull('shipped_at')
            ->whereNotNull('delivered_at')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->select('shipping_cost', 'shipped_at', 'delivered_at')
            ->get();

        $totalOrders = $shippedOrders->count();
        $totalShippingCost = $shippedOrders->sum('shipping_cost');

        $totalDeliveryDays = 0;
        foreach ($shippedOrders as $order) {
            $totalDeliveryDays += $order->shipped_at->diffInDays($order->delivered_at);
        }

        return [
            'avg_shipping_cost' => $totalOrders > 0 ? round($totalShippingCost / $totalOrders, 0) : 0,
            'avg_delivery_days' => $totalOrders > 0 ? round($totalDeliveryDays / $totalOrders, 1) : 0,
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

    /** Top 10 products by quantity sold, with current stock availability badge */
    public function getTopProducts(string $startDate, string $endDate, int $limit = 10): array
    {
        $topProducts = DB::table('order_items as oi')
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
            ->get();

        if ($topProducts->isEmpty()) {
            return [];
        }

        $stockByProduct = DB::table('product_variants')
            ->whereIn('product_id', $topProducts->pluck('id'))
            ->where('is_active', true)
            ->groupBy('product_id')
            ->select('product_id', DB::raw('MIN(stock - reserved_stock) as available_stock'))
            ->pluck('available_stock', 'product_id');

        return $topProducts->map(function ($product) use ($stockByProduct) {
            $availableStock = (int) ($stockByProduct[$product->id] ?? 0);
            $product->available_stock = $availableStock;
            $product->stock_status = $this->resolveStockStatus($availableStock);
            return (array) $product;
        })->toArray();
    }

    /** Resolve stock badge tier based on the configured low-stock threshold */
    protected function resolveStockStatus(int $availableStock): string
    {
        if ($availableStock <= 0) return 'habis';
        if ($availableStock <= $this->lowStockThreshold) return 'kritis';
        if ($availableStock <= $this->lowStockThreshold * 2) return 'perhatian';
        return 'aman';
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
     * §1a / tracker #45 (DESIGN-LOCKED): structured KPI trend display.
     *
     * Bucket A ('currency'/'count'):
     *   prev==0 && cur==0 -> {type: 'none', display: '—'}
     *   prev==0 && cur>0  -> {type: 'new', display: 'Baru'}
     *   prev>0: pct = (cur-prev)/prev*100; |pct|>200 -> signed raw delta formatted per
     *           unit (e.g. '+33', '+Rp 4.450.000'), else -> 'pct%'.
     *
     * Bucket B ('rate' — checkout_to_paid_rate, order_return_rate): always a signed
     * delta in percentage points (e.g. '+8 pp'), never a ratio-of-ratio.
     *
     * Pure display — current/previous values themselves are unchanged (financial-safe).
     */
    protected function calculateTrend(float $current, float $previous, string $bucket = 'count'): array
    {
        if ($bucket === 'rate') {
            $delta = round($current - $previous, 1);
            $sign = $delta >= 0 ? '+' : '-';

            return [
                'type' => 'delta_points',
                'value' => $delta,
                'display' => $sign . $this->formatTrendNumber(abs($delta)) . ' pp',
            ];
        }

        if ($previous == 0.0 && $current == 0.0) {
            return ['type' => 'none', 'value' => null, 'display' => '—'];
        }

        if ($previous == 0.0 && $current > 0) {
            return ['type' => 'new', 'value' => null, 'display' => 'Baru'];
        }

        $pct = round((($current - $previous) / $previous) * 100, 1);

        if (abs($pct) > 200) {
            $delta = $current - $previous;
            $sign = $delta >= 0 ? '+' : '-';
            $formatted = number_format(abs($delta), 0, ',', '.');

            return [
                'type' => 'delta',
                'value' => $delta,
                'display' => $sign . ($bucket === 'currency' ? "Rp {$formatted}" : $formatted),
            ];
        }

        $sign = $pct >= 0 ? '+' : '-';

        return [
            'type' => 'percent',
            'value' => $pct,
            'display' => $sign . $this->formatTrendNumber(abs($pct)) . '%',
        ];
    }

    /**
     * Format a trend magnitude without a redundant ".0" (e.g. 33.0 -> "33", 8.5 -> "8,5").
     */
    protected function formatTrendNumber(float $value, int $maxDecimals = 1): string
    {
        $rounded = round($value, $maxDecimals);

        if ($rounded == floor($rounded)) {
            return number_format($rounded, 0, ',', '.');
        }

        return number_format($rounded, $maxDecimals, ',', '.');
    }

    /**
     * KPI Context (§1a, digabung ke C2): label periode pembanding untuk ditampilkan
     * di sebelah delta tren, mis. "vs 7 hari sebelumnya".
     */
    public function resolveComparisonLabel(string $period, string $comparePeriod, string $prevStartDate, string $prevEndDate): string
    {
        if ($comparePeriod === 'previous_year') {
            return 'vs tahun lalu';
        }

        if (str_contains($period, '|')) {
            $start = \Carbon\Carbon::parse($prevStartDate)->format('d M');
            $end = \Carbon\Carbon::parse($prevEndDate)->format('d M');

            return "vs periode sebelumnya ({$start} - {$end})";
        }

        return match ($period) {
            'today'     => 'vs kemarin',
            'yesterday' => 'vs 2 hari lalu',
            '7days'     => 'vs 7 hari sebelumnya',
            '30days'    => 'vs 30 hari sebelumnya',
            'week'      => 'vs minggu lalu',
            'month'     => 'vs bulan lalu',
            'year'      => 'vs tahun lalu',
            default     => 'vs periode sebelumnya',
        };
    }
}
