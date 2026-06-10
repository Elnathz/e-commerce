<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExportJob;
use App\Models\Order;
use App\Services\AnalyticsDashboardService;
use App\Jobs\ExportOrdersJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsDashboardService $analyticsService
    ) {}

    public function dashboard(Request $request, \App\Services\Alerting\SystemAlertService $alertService)
    {
        $period = $request->query('period', 'month');
        $comparePeriod = $request->query('compare_period', 'previous_period');
        $user = Auth::user();
        
        $metrics = [];
        // Karena di database role hanya 'admin' dan 'customer', kita anggap 'admin' memiliki akses penuh (sebagai owner/pengelola utama).
        $isOwner = in_array($user->role, ['owner', 'superadmin', 'admin']);

        // Jika Operational Dashboard
        $operational = $this->analyticsService->getOperationalMetrics();
        $lowStockProducts = $this->analyticsService->getLowStockProducts(10);

        [$periodStart, $periodEnd] = $this->analyticsService->resolvePeriod($period);

        if ($isOwner) {
            // Hanya owner yang boleh menarik data finansial (simulasi pemisahan service)
            $financial = $this->analyticsService->getFinancialMetrics($period, $comparePeriod);
        } else {
            $financial = [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'gross_sales' => 0,
                'gross_sales_trend' => 0,
                'total_refund' => 0,
                'total_refund_trend' => 0,
                'checkout_to_paid_rate' => 0,
                'checkout_to_paid_rate_trend' => 0,
                'order_return_rate' => 0,
                'order_return_rate_trend' => 0,
                'repeat_customer_rate' => 0,

                // Non-financial metrics that should still be visible
                'payment_summary' => $this->analyticsService->getPaymentSummary($periodStart, $periodEnd),
                'logistics_performance' => $this->analyticsService->getLogisticsPerformance($periodStart, $periodEnd),
                'sales_chart' => $this->analyticsService->getSalesChartData($periodStart, $periodEnd, $period),
            ];
            
            // Catat jika bukan owner tapi mencoba mengakses API endpoint spesifik revenue (jika dibuat)
            // Namun karena ini digabung di render Inertia, kita hindari me-load data finansial.
        }

        return Inertia::render('Admin/Dashboard', [
            'metrics' => array_merge($operational, $financial, [
                'period' => $period,
                'compare_period' => $comparePeriod,
            ]),
            'lowStockProducts' => $lowStockProducts,
            'isOwner' => $isOwner,
        ]);
    }

    public function refresh(Request $request)
    {
        $user = Auth::user();
        $rateLimitKey = 'refresh_dashboard_' . $user->id;

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($rateLimitKey);
            return back()->with('error', "Terlalu sering. Harap tunggu {$seconds} detik.");
        }

        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, 30);

        $period = $request->input('period', 'month');
        $comparePeriod = $request->input('compare_period', 'previous_period');
        $this->analyticsService->clearFinancialCache($period, $comparePeriod);

        return back()->with('success', 'Data dashboard berhasil diperbarui.');
    }

    public function getRevenueData(Request $request, \App\Services\Alerting\SystemAlertService $alertService)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['owner', 'superadmin'])) {
            $alertService->dispatch('Privilege Escalation Attempt', 'warning', [
                'user_id' => $user->id,
                'email' => $user->email,
                'action' => 'Attempted to access revenue endpoint',
                'ip' => $request->ip()
            ]);
            abort(403, 'Unauthorized access to financial data.');
        }

        return response()->json($this->analyticsService->getFinancialMetrics($request->query('period', 'month')));
    }

    public function exports()
    {
        $jobs = ExportJob::where('user_id', Auth::id())
            ->latest()
            ->take(20)
            ->get();

        return response()->json($jobs);
    }

    public function startExport(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'status'     => 'nullable|string',
        ]);

        $filters = $request->only(['start_date', 'end_date', 'status']);
        $userId = Auth::id();

        // 1. Quota Check (Max 10 per day)
        $dailyCount = ExportJob::where('user_id', $userId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($dailyCount >= 10) {
            return response()->json([
                'message' => 'Anda telah mencapai batas 10 export per hari.'
            ], 422);
        }

        // 2. Concurrent Check (Max 3 active)
        $activeCount = ExportJob::where('user_id', $userId)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($activeCount >= 3) {
            return response()->json([
                'message' => 'Anda sudah memiliki 3 export yang sedang berjalan.'
            ], 422);
        }

        // 3. Size Estimate (Max 100k rows)
        $query = Order::query();
        if (!empty($filters['start_date'])) $query->whereDate('created_at', '>=', $filters['start_date']);
        if (!empty($filters['end_date'])) $query->whereDate('created_at', '<=', $filters['end_date']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);

        $estimatedRows = $query->count();

        if ($estimatedRows > 100000) {
            return response()->json([
                'message' => 'Data terlalu besar. Maksimal 100.000 baris per export.',
                'estimated_rows' => $estimatedRows,
                'suggestion' => 'Gunakan filter tanggal yang lebih sempit.'
            ], 422);
        }

        $job = ExportJob::create([
            'user_id' => $userId,
            'type'    => 'orders',
            'status'  => 'pending',
            'filters' => $filters,
            'estimated_rows' => $estimatedRows,
            'expires_at' => now()->addHours(24),
        ]);

        ExportOrdersJob::dispatch($job, $filters);

        return response()->json([
            'message' => 'Export dimulai di latar belakang. Silakan cek riwayat export dalam beberapa menit.',
            'job_id'  => $job->id,
        ], 202);
    }

    public function downloadExport(ExportJob $job)
    {
        if ($job->user_id !== Auth::id()) {
            abort(403);
        }

        if ($job->isExpired()) {
            abort(410, 'File export sudah kadaluarsa dan telah dihapus.');
        }

        if (!$job->isDownloadable()) {
            abort(404, 'File export tidak ditemukan atau belum selesai.');
        }

        return Storage::download($job->file_path);
    }
}
