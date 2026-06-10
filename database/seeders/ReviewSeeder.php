<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\Review;
use Carbon\Carbon;

class ReviewSeeder extends Seeder
{
    public function run()
    {
        $orders = Order::where('status', 'completed')->with('items')->get();
        if ($orders->isEmpty()) return;

        $scenarios = [
            ['rating' => 5, 'comment' => 'Barang sangat bagus dan pengiriman cepat! Terima kasih.', 'replied' => true],
            ['rating' => 5, 'comment' => 'Mantap, sesuai deskripsi.', 'replied' => false],
            ['rating' => 4, 'comment' => 'Kualitas oke, tapi box sedikit penyok.', 'replied' => false],
            ['rating' => 3, 'comment' => 'Barang biasa saja, tidak ada yang spesial.', 'replied' => false],
            ['rating' => 2, 'comment' => 'Pengiriman sangat lama dan barang agak lecet.', 'replied' => false],
            ['rating' => 1, 'comment' => 'Barang rusak tidak bisa dipakai, tolong diretur!', 'replied' => true],
        ];

        $scenarioIndex = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                // Ensure no duplicate reviews for this order item
                if (Review::where('order_item_id', $item->id)->exists()) {
                    continue;
                }

                $scenario = $scenarios[$scenarioIndex % count($scenarios)];
                
                $adminReply = null;
                $repliedAt = null;

                if ($scenario['replied']) {
                    if ($scenario['rating'] == 5) {
                        $adminReply = "Terima kasih banyak atas ulasan positifnya, Kak! Ditunggu pesanan selanjutnya.";
                    } else {
                        $adminReply = "Mohon maaf atas ketidaknyamanannya, Kak. Tim kami akan segera menghubungi untuk proses lebih lanjut.";
                    }
                    $repliedAt = Carbon::now()->subDays(rand(1, 5));
                }

                $createdAt = $order->delivered_at ? clone $order->delivered_at : Carbon::parse($order->created_at)->addDays(4);

                $variant = \App\Models\ProductVariant::find($item->product_variant_id);
                if (!$variant) continue;

                Review::create([
                    'order_item_id' => $item->id,
                    'user_id' => $order->user_id,
                    'product_id' => $variant->product_id,
                    'rating' => $scenario['rating'],
                    'comment' => $scenario['comment'],
                    'is_published' => true,
                    'admin_reply' => $adminReply,
                    'replied_at' => $repliedAt,
                    'created_at' => $createdAt->addDays(rand(1, 3)),
                    'updated_at' => $createdAt,
                ]);

                $scenarioIndex++;
            }
        }
    }
}
