<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Exception;

class ExportOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour max

    public function __construct(
        protected ExportJob $exportJob,
        protected array $filters
    ) {}

    public function handle(): void
    {
        $this->exportJob->update([
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        try {
            $query = Order::query()->with(['user', 'items.variant.product']);

            // Apply filters
            if (!empty($this->filters['start_date'])) {
                $query->whereDate('created_at', '>=', $this->filters['start_date']);
            }
            if (!empty($this->filters['end_date'])) {
                $query->whereDate('created_at', '<=', $this->filters['end_date']);
            }
            if (!empty($this->filters['status'])) {
                $query->where('status', $this->filters['status']);
            }

            $filename = 'exports/orders_' . $this->exportJob->id . '_' . time() . '.csv';
            
            // We use a temporary file to build the CSV
            $tempPath = tempnam(sys_get_temp_dir(), 'export_');
            $file = fopen($tempPath, 'w');

            // Write headers
            fputcsv($file, [
                'Order ID', 'Order Number', 'Date', 'Customer Name', 'Customer Email',
                'Status', 'Payment Method', 'Payment Status', 'Subtotal', 'Shipping Cost',
                'Discount Amount', 'Total Amount', 'Courier', 'Tracking Number'
            ]);

            $rowCount = 0;
            
            // Process lazily with cursor to prevent RAM ballooning with eager loading
            foreach ($query->cursor() as $order) {
                fputcsv($file, [
                    $order->id,
                    $order->order_number,
                    $order->created_at->format('Y-m-d H:i:s'),
                    $order->user->name ?? 'Guest',
                    $order->user->email ?? '-',
                    $order->status,
                    $order->payment_method,
                    $order->payment_status,
                    $order->subtotal,
                    $order->shipping_cost,
                    $order->discount_amount,
                    $order->total_amount,
                    $order->courier,
                    $order->tracking_number,
                ]);
                $rowCount++;
            }

            fclose($file);

            // Move to storage
            Storage::put($filename, file_get_contents($tempPath));
            unlink($tempPath);

            $fileSize = Storage::size($filename);

            $this->exportJob->update([
                'status'          => 'completed',
                'file_path'       => $filename,
                'file_size_bytes' => $fileSize,
                'row_count'       => $rowCount,
                'completed_at'    => now(),
            ]);

        } catch (Exception $e) {
            $this->exportJob->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
            throw $e;
        }
    }
}
