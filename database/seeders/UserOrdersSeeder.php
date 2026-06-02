<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Payment;
use App\Models\ReturnRequest;
use Carbon\Carbon;
use Illuminate\Support\Str;

class UserOrdersSeeder extends Seeder
{
    public function run()
    {
        $user = User::where('email', 'farrosrifantiarno32@gmail.com')->first();
        if (!$user) {
            $this->command->error("User farrosrifantiarno32@gmail.com not found!");
            return;
        }

        $products = Product::inRandomOrder()->limit(5)->get();
        if ($products->count() < 1) {
            $this->command->error("No products found! Seed products first.");
            return;
        }

        $cases = [
            [
                'desc' => 'Sedang Diproses',
                'status' => 'processing',
                'has_return' => false,
            ],
            [
                'desc' => 'Dikirim',
                'status' => 'shipped',
                'has_return' => false,
            ],
            [
                'desc' => 'Selesai',
                'status' => 'completed',
                'has_return' => false,
            ],
            [
                'desc' => 'Retur',
                'status' => 'completed',
                'has_return' => true,
            ],
            [
                'desc' => 'Dibatalkan',
                'status' => 'cancelled',
                'has_return' => false,
            ],
        ];

        $totalSeeded = 0;

        foreach ($cases as $index => $case) {
            $numOrders = rand(8, 15);
            for ($i = 0; $i < $numOrders; $i++) {
                $product = $products->random();
                $quantity = rand(1, 4);
                $subtotal = $product->base_price * $quantity;
                $shipping_cost = rand(1, 3) * 10000;
                $total_amount = $subtotal + $shipping_cost;

                $is_cancelled = $case['status'] === 'cancelled';
                $paid_at = $is_cancelled ? null : Carbon::now()->subDays(rand(1, 30));
                $payment_status = $is_cancelled ? 'failed' : 'paid';

            $addressSnapshot = [
                'name' => $user->name,
                'phone' => '081234567890',
                'address' => 'Jl. E-Commerce No. ' . rand(1, 100),
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12345'
            ];

            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                'user_id' => $user->id,
                'status' => $case['status'],
                'fulfillment_type' => 'delivery',
                'subtotal' => $subtotal,
                'shipping_cost' => $shipping_cost,
                'discount_amount' => 0,
                'total_amount' => $total_amount,
                'shipping_address_snapshot' => $addressSnapshot,
                'courier' => 'jne',
                'tracking_number' => 'JNE' . strtoupper(Str::random(12)),
                'created_at' => Carbon::now()->subDays(rand(5, 30)),
                'updated_at' => Carbon::now()->subDays(rand(1, 4)),
            ]);

            $variant = $product->variants()->first();

            OrderItem::create([
                'order_id' => $order->id,
                'product_variant_id' => $variant ? $variant->id : null,
                'product_name_snapshot' => $product->name,
                'variant_name_snapshot' => $variant ? $variant->sku : null,
                'quantity' => $quantity,
                'unit_price' => $product->base_price,
                'weight_gram' => 500,
                'subtotal' => $subtotal,
            ]);

            if ($payment_status === 'paid') {
                Payment::create([
                    'order_id' => $order->id,
                    'merchant_ref' => 'REF-' . strtoupper(Str::random(10)),
                    'payment_method' => 'bank_transfer',
                    'payment_channel' => 'BCA',
                    'payment_name' => 'BCA Virtual Account',
                    'amount' => $total_amount,
                    'fee_amount' => 4000,
                    'status' => 'paid',
                    'paid_at' => $paid_at,
                ]);
            }

                if ($case['has_return']) {
                    $productImages = $product->images()->pluck('image_path')->toArray();
                    $evidence1 = $productImages[0] ?? 'returns/dummy1.jpg';
                    $evidence2 = $productImages[1] ?? (count($productImages) > 0 ? $productImages[0] : null);
                    $evidence3 = $productImages[2] ?? null;

                    ReturnRequest::create([
                        'return_number' => 'RET-' . strtoupper(Str::random(10)),
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'status' => 'submitted',
                        'reason' => 'Barang yang diterima rusak di jalan atau tidak sesuai',
                        'is_partial' => false,
                        'evidence_image_1' => $evidence1,
                        'evidence_image_2' => $evidence2,
                        'evidence_image_3' => $evidence3,
                        'refund_amount' => $total_amount,
                        'refund_method' => 'bank_transfer',
                    ]);
                }
                
                $totalSeeded++;
            }
        }

        $this->command->info("Seeded {$totalSeeded} specific orders for user {$user->email}!");
    }
}
