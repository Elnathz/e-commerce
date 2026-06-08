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
        $user = Auth::user();
        
        $metrics = [];
        // Asumsi: jika role tidak ada/kosong, kita anggap sebagai admin biasa/gudang. 
        // Jika owner/superadmin, dia punya role tertentu. Karena di tabel users ada kolom `role`, kita akan periksa:
        $isOwner = in_array($user->role, ['owner', 'superadmin']);

        // Jika Operational Dashboard
        $metrics['operational'] = $this->analyticsService->getOperationalMetrics();
        $lowStockProducts = $this->analyticsService->getLowStockProducts(10);

        if ($isOwner) {
            // Hanya owner yang boleh menarik data finansial (simulasi pemisahan service)
            $metrics['financial'] = $this->analyticsService->getFinancialMetrics($period);
        } else {
            $metrics['financial'] = [
                'gross_sales' => 0,
                'total_refund' => 0,
                'checkout_to_paid_rate' => 0,
                'return_rate' => 0,
                'repeat_customer_rate' => 0,
            ];
            
            // Catat jika bukan owner tapi mencoba mengakses API endpoint spesifik revenue (jika dibuat)
            // Namun karena ini digabung di render Inertia, kita hindari me-load data finansial.
        }

        return Inertia::render('Admin/Dashboard', [
            'metrics' => $metrics,
            'lowStockProducts' => $lowStockProducts,
            'isOwner' => $isOwner,
        ]);
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
