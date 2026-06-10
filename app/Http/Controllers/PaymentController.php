<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Services\Payment\IPaymuService;
use App\Traits\DispatchesAtomicNotification;
use App\Notifications\OrderStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use DispatchesAtomicNotification;

    /**
     * GET /api/payment-channels
     * Return list of available payment channels.
     */
    public function getChannels()
    {
        return response()->json([
            'success' => true,
            'channels' => IPaymuService::getAvailableChannels(),
        ]);
    }

    /**
     * POST /payments/{order_number}/pay
     * Create a payment invoice via iPaymu Direct Payment.
     *
     * FR014: Multiple payment methods support
     */
    public function createPayment(Request $request, string $order_number, IPaymuService $ipaymu)
    {
        $request->validate([
            'payment_method' => 'required|string|in:va,qris,cstore',
            'payment_channel' => 'required|string',
        ]);

        $order = Order::where('order_number', $order_number)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Validate order is still payable
        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan ini sudah dibayar.',
            ], 400);
        }

        if ($order->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan ini sudah dibatalkan.',
            ], 400);
        }

        if ($order->expired_at && $order->expired_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Batas waktu pembayaran telah habis.',
            ], 400);
        }

        // Check if there's already a pending payment — prevent duplicate invoices
        $existingPayment = $order->payments()->where('status', 'pending')->latest()->first();
        if ($existingPayment) {
            return response()->json([
                'success' => true,
                'message' => 'Invoice pembayaran sudah ada.',
                'payment' => $existingPayment,
            ]);
        }

        // Call iPaymu API
        $result = $ipaymu->createDirectPayment(
            $order,
            $request->payment_method,
            $request->payment_channel
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 500);
        }

        $data = $result['data'];

        // Save payment record
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway_reference' => $data['TransactionId'] ?? null,
            'merchant_ref' => $order->order_number,
            'payment_method' => $request->payment_method,
            'payment_channel' => $request->payment_channel,
            'payment_name' => $data['PaymentName'] ?? ucfirst($request->payment_channel),
            'amount' => $order->total_amount,
            'fee_amount' => $data['Fee'] ?? 0,
            'status' => 'pending',
            'pay_code' => $data['PaymentNo'] ?? null,
            'pay_url' => $data['PaymentUrl'] ?? null,
            'expired_at' => isset($data['Expired'])
                ? \Carbon\Carbon::parse($data['Expired'])
                : $order->expired_at,
        ]);

        // Update order with payment method info
        $order->update([
            'payment_method' => $request->payment_channel,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice pembayaran berhasil dibuat.',
            'payment' => $payment,
        ]);
    }

    /**
     * GET /payments/{order_number}/status
     * Check current payment status from DB (and optionally from iPaymu API).
     */
    public function checkStatus(string $order_number, IPaymuService $ipaymu)
    {
        $order = Order::where('order_number', $order_number)
            ->where('user_id', Auth::id())
            ->with('activePayment')
            ->firstOrFail();

        $payment = $order->payments()->latest()->first();

        if (!$payment) {
            return response()->json([
                'success' => true,
                'order_status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'order_status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'payment_name' => $payment->payment_name,
                'pay_code' => $payment->pay_code,
                'pay_url' => $payment->pay_url,
                'amount' => $payment->amount,
                'expired_at' => $payment->expired_at?->toIso8601String(),
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/payments/webhook
     * Receive and process callback from iPaymu.
     *
     * FR015: Idempotency — duplicate notifications are ignored
     * FR010: Stock finalization on payment success
     */
    public function webhook(Request $request, IPaymuService $ipaymu)
    {
        // Parse incoming data (JSON or form-data)
        $data = $request->all();

        Log::info('iPaymu webhook received', [
            'reference_id' => $data['reference_id'] ?? 'unknown',
            'status' => $data['status'] ?? 'unknown',
            'trx_id' => $data['trx_id'] ?? 'unknown',
        ]);

        // Verify signature
        if (!$ipaymu->verifyCallbackSignature($request)) {
            Log::warning('iPaymu webhook: invalid signature rejected', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $referenceId = $data['reference_id'] ?? null;  // Our order_number
        $trxId = $data['trx_id'] ?? null;               // iPaymu transaction ID
        $status = $data['status'] ?? null;               // berhasil, pending, expired, gagal
        $statusCode = $data['status_code'] ?? null;

        if (!$referenceId || !$status) {
            Log::warning('iPaymu webhook: missing required fields');
            return response()->json(['message' => 'Missing required fields'], 400);
        }

        // Find payment record
        $payment = Payment::where('merchant_ref', $referenceId)
            ->latest()
            ->first();

        if (!$payment) {
            Log::warning('iPaymu webhook: payment not found', [
                'reference_id' => $referenceId,
            ]);
            // Still return 200 to prevent iPaymu from retrying
            return response()->json(['message' => 'Payment not found'], 200);
        }

        // FR015: Idempotency check — skip if already finalized
        if ($payment->isFinalized()) {
            Log::info('iPaymu webhook: payment already finalized, skipping', [
                'reference_id' => $referenceId,
                'current_status' => $payment->status,
            ]);
            return response()->json(['status' => 'ok']);
        }

        $order = $payment->order;

        try {
            DB::transaction(function () use ($payment, $order, $status, $trxId, $data) {
                // Lock the order row to prevent race conditions
                $order = Order::where('id', $order->id)->lockForUpdate()->first();

                if ($status === 'berhasil') {
                    $this->handlePaymentSuccess($payment, $order, $trxId, $data);
                } elseif (in_array($status, ['expired', 'gagal'])) {
                    $this->handlePaymentFailure($payment, $order, $status, $data);
                }
            });
        } catch (\Exception $e) {
            Log::error('iPaymu webhook processing error', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);
            // Still return 200 — we don't want iPaymu to retry on our internal errors
            // The reconciliation command will catch missed updates
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle successful payment (FR010: stock finalization)
     */
    private function handlePaymentSuccess(Payment $payment, Order $order, $trxId, array $data): void
    {
        // Update payment record
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'gateway_reference' => $trxId ?? $payment->gateway_reference,
            'callback_payload' => array_intersect_key($data, array_flip([
                'trx_id', 'status', 'status_code', 'reference_id', 'amount',
            ])),
        ]);

        $fromStatus = $order->status;

        // Update order status
        $order->update([
            'status' => 'paid',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $payment->payment_channel,
        ]);

        event(new \App\Events\OrderStatusChanged($order, $fromStatus, 'paid'));

        // FR010: Convert stock reservation to permanent deduction
        // reserved_stock -= qty AND stock -= qty (atomic)
        $orderItems = $order->items;
        foreach ($orderItems as $item) {
            $affected = ProductVariant::where('id', $item->product_variant_id)
                ->where('reserved_stock', '>=', $item->quantity)
                ->where('stock', '>=', $item->quantity)
                ->update([
                    'stock' => DB::raw('stock - ' . (int) $item->quantity),
                    'reserved_stock' => DB::raw('reserved_stock - ' . (int) $item->quantity),
                ]);

            if ($affected === 0) {
                Log::error('FR010: Stock finalization failed — inconsistent state', [
                    'order_number' => $order->order_number,
                    'variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                ]);
            }
        }

        // Sprint 9: Confirm Promotion Usage (Phase 2A)
        app(\App\Services\PromotionService::class)->confirm($order->id);

        Log::info('Payment successful: stock finalized', [
            'order_number' => $order->order_number,
            'items_count' => $orderItems->count(),
        ]);

        // Sprint 9: Dispatch Notification atomically
        $eventKey = "order_paid_notification_{$order->id}";
        $this->dispatchAtomicNotification($eventKey, function () use ($order) {
            $order->user->notify(new OrderStatusNotification(
                $order,
                'order_paid',
                'Pembayaran Berhasil Diterima',
                'Hore! Pembayaran untuk pesanan ' . $order->order_number . ' telah kami terima. Kami akan segera memproses pesanan Anda.'
            ));
        });
    }

    /**
     * Handle failed/expired payment — release reserved stock
     */
    private function handlePaymentFailure(Payment $payment, Order $order, string $status, array $data): void
    {
        $paymentStatus = $status === 'expired' ? 'expired' : 'failed';

        // Update payment record
        $payment->update([
            'status' => $paymentStatus,
            'callback_payload' => array_intersect_key($data, array_flip([
                'trx_id', 'status', 'status_code', 'reference_id',
            ])),
        ]);

        // Check if there are other pending payments for this order
        $otherPendingPayments = $order->payments()
            ->where('id', '!=', $payment->id)
            ->where('status', 'pending')
            ->count();

        // If no other pending payments, cancel order and release stock
        if ($otherPendingPayments === 0) {
            // Release reserved stock
            $orderItems = $order->items;
            foreach ($orderItems as $item) {
                ProductVariant::where('id', $item->product_variant_id)
                    ->where('reserved_stock', '>=', $item->quantity)
                    ->update([
                        'reserved_stock' => DB::raw('reserved_stock - ' . (int) $item->quantity),
                    ]);
            }

            $fromStatus = $order->status;

            $order->update([
                'status' => 'cancelled',
                'payment_status' => 'failed',
                'cancelled_at' => now(),
                'cancelled_reason' => $status === 'expired'
                    ? 'Batas waktu pembayaran habis'
                    : 'Pembayaran gagal',
            ]);

            event(new \App\Events\OrderStatusChanged($order, $fromStatus, 'cancelled'));

            // Sprint 9: Release Promotion Usage (Phase 2B)
            app(\App\Services\PromotionService::class)->release($order->id);

            Log::info('Payment failed/expired: stock released, order cancelled', [
                'order_number' => $order->order_number,
                'reason' => $status,
            ]);

            // Sprint 9: Dispatch Notification atomically
            $eventKey = "order_cancelled_notification_{$order->id}";
            $this->dispatchAtomicNotification($eventKey, function () use ($order, $status) {
                $order->user->notify(new OrderStatusNotification(
                    $order,
                    'order_cancelled',
                    'Pesanan Dibatalkan',
                    $status === 'expired'
                        ? 'Pesanan ' . $order->order_number . ' telah dibatalkan secara otomatis karena melewati batas waktu pembayaran.'
                        : 'Pesanan ' . $order->order_number . ' dibatalkan karena pembayaran gagal.'
                ));
            });
        }
    }
}
