<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Services\AnalyticsDashboardService;

class AnalyticsAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_gross_sales_and_net_revenue_accuracy()
    {
        $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'name' => 'Test User', 'email' => 'test@test.com', 'password' => bcrypt('password'), 'role' => 'customer'
        ]);

        $orderAId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'ORD-A', 'user_id' => $userId, 'status' => 'refunded', 'payment_status' => 'paid', 'subtotal' => 100000, 'total_amount' => 100000, 'fulfillment_type' => 'delivery', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now()
        ]);

        \Illuminate\Support\Facades\DB::table('orders')->insert([
            'order_number' => 'ORD-B', 'user_id' => $userId, 'status' => 'completed', 'payment_status' => 'paid', 'subtotal' => 200000, 'total_amount' => 200000, 'fulfillment_type' => 'delivery', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now()
        ]);

        \Illuminate\Support\Facades\DB::table('orders')->insert([
            'order_number' => 'ORD-C', 'user_id' => $userId, 'status' => 'pending', 'payment_status' => 'unpaid', 'subtotal' => 150000, 'total_amount' => 150000, 'fulfillment_type' => 'delivery', 'created_at' => now(), 'updated_at' => now()
        ]);

        $orderAItemId = \Illuminate\Support\Facades\DB::table('order_items')->insertGetId([
            'order_id' => $orderAId, 'product_variant_id' => null, 'product_name_snapshot' => 'Product A', 'quantity' => 1, 'unit_price' => 100000, 'weight_gram' => 100, 'subtotal' => 100000, 'created_at' => now(), 'updated_at' => now()
        ]);

        $returnReqId = \Illuminate\Support\Facades\DB::table('return_requests')->insertGetId([
            'return_number' => 'RET-A', 'order_id' => $orderAId, 'user_id' => $userId, 'status' => 'refund_processed', 'reason' => 'Barang rusak', 'evidence_image_1' => 'evidence/a.jpg', 'refund_processed_at' => now(), 'created_at' => now(), 'updated_at' => now()
        ]);

        \Illuminate\Support\Facades\DB::table('return_request_items')->insert([
            'return_request_id' => $returnReqId, 'order_item_id' => $orderAItemId, 'quantity' => 1, 'reason_code' => 'defective', 'condition' => 'damaged', 'refund_amount' => 50000, 'created_at' => now(), 'updated_at' => now()
        ]);

        $service = new AnalyticsDashboardService();
        $metrics = $service->getFinancialMetrics('today');

        $grossSales = $metrics['gross_sales'];
        $totalRefund = $metrics['total_refund'];
        $netRevenue = $grossSales - $totalRefund;

        $this->assertEquals(300000, $grossSales, 'Gross sales should only count paid/completed/refunded orders (Order A + Order B)');
        $this->assertEquals(50000, $totalRefund, 'Total refund should be 50.000');
        $this->assertEquals(250000, $netRevenue, 'Net revenue should be 250.000');
    }
}
