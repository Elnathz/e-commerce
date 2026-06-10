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
        $user = User::where('role', 'admin')->first() ?? User::first();
        if (!$user) {
            $this->command->error("No user found!");
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
            [
                'desc' => 'Need Fulfillment',
                'status' => 'paid',
                'has_return' => false,
            ],
            [
                'desc' => 'Inspeksi Retur',
                'status' => 'completed',
                'has_return' => true,
                'return_status' => 'received',
            ],
            [
                'desc' => 'Refund Keluar',
                'status' => 'completed',
                'has_return' => true,
                'return_status' => 'completed',
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
                
                // Spread created_at realistically, heavily weighted towards the last 7 days and specific milestones
                $random = rand(1, 100);
                if ($random <= 15) {
                    $createdAt = Carbon::today()->subHours(rand(1, 5));
                } elseif ($random <= 30) {
                    $createdAt = Carbon::yesterday()->subHours(rand(1, 5));
                } elseif ($random <= 45) {
                    $createdAt = Carbon::now()->subDays(7)->subHours(rand(1, 5));
                } else {
                    $createdAt = Carbon::now()->subDays(rand(1, 360))->subHours(rand(1, 23));
                }

                $paid_at = $is_cancelled ? null : (clone $createdAt)->addHours(rand(1, 12));
                
                $shipped_at = null;
                $delivered_at = null;
                
                if (in_array($case['status'], ['shipped', 'completed'])) {
                    $shipped_at = (clone $createdAt)->addDays(rand(1, 2));
                }
                
                if ($case['status'] === 'completed') {
                    $delivered_at = (clone $shipped_at)->addDays(rand(2, 5));
                }

                $payment_status = $is_cancelled ? 'failed' : 'paid';

                $paymentMethods = ['qris', 'va_bca', 'va_mandiri', 'va_bni', 'e_wallet'];
                $chosenMethod = $paymentMethods[array_rand($paymentMethods)];

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
                'payment_method' => $payment_status === 'paid' ? $chosenMethod : null,
                'payment_status' => $payment_status,
                'paid_at' => $paid_at,
                'shipped_at' => $shipped_at,
                'delivered_at' => $delivered_at,
                'created_at' => $createdAt,
                'updated_at' => $delivered_at ?? ($shipped_at ?? ($paid_at ?? $createdAt)),
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
                    'payment_method' => $chosenMethod,
                    'payment_channel' => strtoupper(str_replace(['va_', '_'], ['', ' '], $chosenMethod)),
                    'payment_name' => 'Payment ' . strtoupper($chosenMethod),
                    'amount' => $total_amount,
                    'fee_amount' => 4000,
                    'status' => 'paid',
                    'paid_at' => $paid_at,
                ]);

                // Decrement stock if paid but prevent unsigned error
                if ($variant && $variant->stock >= $quantity) {
                    $variant->decrement('stock', $quantity);
                } elseif ($variant) {
                    $variant->update(['stock' => 0]);
                }
            }

                if ($case['has_return']) {
                    $productImages = $product->images()->pluck('image_path')->toArray();
                    $evidence1 = $productImages[0] ?? 'returns/dummy1.jpg';
                    $evidence2 = $productImages[1] ?? (count($productImages) > 0 ? $productImages[0] : null);
                    $evidence3 = $productImages[2] ?? null;

                    $returnStatus = $case['return_status'] ?? 'submitted';
                    $refundProcessedAt = $returnStatus === 'completed' ? (clone $createdAt)->addDays(rand(2, 5)) : null;
                    
                    $returnRequest = ReturnRequest::create([
                        'return_number' => 'RET-' . strtoupper(Str::random(10)),
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'status' => $returnStatus,
                        'reason' => 'Barang yang diterima rusak di jalan atau tidak sesuai',
                        'evidence_image_1' => $evidence1,
                        'evidence_image_2' => $evidence2,
                        'evidence_image_3' => $evidence3,
                        'refund_amount' => $returnStatus === 'completed' ? $total_amount : null,
                        'refund_method' => $returnStatus === 'completed' ? 'bank_transfer' : null,
                        'refund_processed_at' => $refundProcessedAt,
                    ]);

                    \App\Models\ReturnRequestItem::create([
                        'return_request_id' => $returnRequest->id,
                        'order_item_id' => $order->items->first()->id,
                        'quantity' => 1,
                        'reason_code' => 'defective',
                        'condition' => 'damaged',
                        'refund_amount' => $returnStatus === 'completed' ? $total_amount : 0,
                    ]);
                }
                
                $totalSeeded++;
            }
        }

        $this->command->info("Seeded {$totalSeeded} specific orders for user {$user->email}!");
    }
}
