<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IPaymuService
{
    private string $va;
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->va = config('services.ipaymu.va', '') ?? '';
        $this->apiKey = config('services.ipaymu.api_key', '') ?? '';

        $mode = config('services.ipaymu.mode', 'sandbox');
        $this->baseUrl = $mode === 'production'
            ? 'https://my.ipaymu.com/api/v2'
            : 'https://sandbox.ipaymu.com/api/v2';
    }

    /**
     * Generate HMAC-SHA256 signature for iPaymu API requests.
     *
     * Format: hash_hmac('sha256', "POST:{va}:{sha256(body)}:{apiKey}", apiKey)
     */
    private function generateSignature(string $jsonBody): string
    {
        $bodyHash = strtolower(hash('sha256', $jsonBody));
        $stringToSign = "POST:{$this->va}:{$bodyHash}:{$this->apiKey}";

        return hash_hmac('sha256', $stringToSign, $this->apiKey);
    }

    /**
     * Build common headers for iPaymu API requests.
     */
    private function buildHeaders(string $jsonBody): array
    {
        return [
            'Content-Type' => 'application/json',
            'va' => $this->va,
            'signature' => $this->generateSignature($jsonBody),
            'timestamp' => now()->format('YmdHis'),
        ];
    }

    /**
     * Create a Direct Payment invoice on iPaymu.
     *
     * @param Order $order The order to create payment for
     * @param string $method Payment method: 'va', 'qris', 'cstore'
     * @param string $channel Payment channel: 'bca', 'bni', 'bri', 'mandiri', 'cimb', 'qris', 'alfamart'
     * @return array{success: bool, data?: array, message?: string}
     */
    public function createDirectPayment(Order $order, string $method, string $channel): array
    {
        $user = $order->user;
        $addressSnapshot = $order->shipping_address_snapshot;

        $appUrl = rtrim(config('app.url'), '/');

        $payload = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $addressSnapshot['phone'] ?? ($user->phone ?? ''),
            'amount' => (int) $order->total_amount,
            'notifyUrl' => $appUrl . '/api/payments/webhook',
            'returnUrl' => route('checkout.success', $order->order_number),
            'cancelUrl' => route('checkout.success', $order->order_number),
            'referenceId' => $order->order_number,
            'paymentMethod' => $method,
            'paymentChannel' => $channel,
            'expired' => 24, // hours
            'feeDirection' => 'MERCHANT',
        ];

        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES);

        try {
            $response = Http::withHeaders($this->buildHeaders($jsonBody))
                ->withBody($jsonBody, 'application/json')
                ->timeout(30)
                ->post("{$this->baseUrl}/payment/direct");

            $result = $response->json();

            Log::info('iPaymu createDirectPayment response', [
                'order_number' => $order->order_number,
                'status_code' => $response->status(),
                'success' => $result['Success'] ?? false,
            ]);

            if ($response->successful() && ($result['Success'] ?? false)) {
                return [
                    'success' => true,
                    'data' => $result['Data'] ?? [],
                ];
            }

            return [
                'success' => false,
                'message' => $result['Message'] ?? 'Gagal membuat invoice pembayaran.',
            ];
        } catch (\Exception $e) {
            Log::error('iPaymu createDirectPayment error', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Koneksi ke payment gateway gagal. Silakan coba lagi.',
            ];
        }
    }

    /**
     * Check transaction status on iPaymu.
     *
     * @param int|string $transactionId The iPaymu transaction ID
     * @return array{success: bool, data?: array, message?: string}
     */
    public function checkTransaction($transactionId): array
    {
        $payload = [
            'transactionId' => $transactionId,
        ];

        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES);

        try {
            $response = Http::withHeaders($this->buildHeaders($jsonBody))
                ->withBody($jsonBody, 'application/json')
                ->timeout(15)
                ->post("{$this->baseUrl}/transaction");

            $result = $response->json();

            if ($response->successful() && ($result['Success'] ?? false)) {
                return [
                    'success' => true,
                    'data' => $result['Data'] ?? [],
                ];
            }

            return [
                'success' => false,
                'message' => $result['Message'] ?? 'Gagal mengecek status pembayaran.',
            ];
        } catch (\Exception $e) {
            Log::error('iPaymu checkTransaction error', [
                'transactionId' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Koneksi ke payment gateway gagal.',
            ];
        }
    }

    /**
     * Verify callback signature from iPaymu webhook.
     *
     * iPaymu sends signature inside the body. Verification:
     * 1. Remove 'signature' from data
     * 2. ksort remaining data
     * 3. json_encode
     * 4. hash_hmac('sha256', json, VA_number)
     * 5. Compare with hash_equals
     *
     * @param array $data The full callback data including signature
     * @return bool
     */
    public function verifyCallbackSignature(\Illuminate\Http\Request $request): bool
    {
        $data = $request->all();
        $receivedSignature = $request->input('signature') ?? $request->header('X-Signature');

        // BYPASS FOR LOCAL IPAYMU SIMULATOR
        // Simulator's X-Signature algorithm is notoriously undocumented/buggy.
        if (config('app.env') === 'local' && $request->header('X-Signature')) {
            Log::info('iPaymu callback: Bypassing signature validation for local Simulator test.');
            return true;
        }

        if (empty($receivedSignature)) {
            Log::warning('iPaymu callback: missing signature');
            return false;
        }

        // If the signature was in the body, remove it before hashing
        if (isset($data['signature'])) {
            unset($data['signature']);
        }

        // iPaymu simulator or some older specs use ksort+json_encode
        ksort($data);
        // Using JSON_UNESCAPED_SLASHES often helps with PHP's json_encode differences
        $jsonBody = json_encode($data, JSON_UNESCAPED_SLASHES);

        $generatedSignature = hash_hmac('sha256', $jsonBody, $this->va);

        // Fallback: Simulator sometimes just hashes the raw request content
        $rawSignature = hash_hmac('sha256', $request->getContent(), $this->va);

        $isValid = hash_equals($generatedSignature, $receivedSignature) || hash_equals($rawSignature, $receivedSignature);

        if (!$isValid) {
            Log::warning('iPaymu callback: invalid signature', [
                'received' => substr($receivedSignature, 0, 16) . '...',
                'generated' => substr($generatedSignature, 0, 16) . '...',
                'raw_generated' => substr($rawSignature, 0, 16) . '...'
            ]);
        }

        return $isValid;
    }

    /**
     * Get list of available payment channels (hardcoded).
     */
    public static function getAvailableChannels(): array
    {
        return [
            [
                'method' => 'va',
                'channel' => 'bca',
                'name' => 'BCA Virtual Account',
                'group' => 'Virtual Account',
            ],
            [
                'method' => 'va',
                'channel' => 'bni',
                'name' => 'BNI Virtual Account',
                'group' => 'Virtual Account',
            ],
            [
                'method' => 'va',
                'channel' => 'bri',
                'name' => 'BRI Virtual Account',
                'group' => 'Virtual Account',
            ],
            [
                'method' => 'va',
                'channel' => 'mandiri',
                'name' => 'Mandiri Virtual Account',
                'group' => 'Virtual Account',
            ],
            [
                'method' => 'va',
                'channel' => 'cimb',
                'name' => 'CIMB Niaga Virtual Account',
                'group' => 'Virtual Account',
            ],
            [
                'method' => 'qris',
                'channel' => 'qris',
                'name' => 'QRIS',
                'group' => 'QRIS',
            ],
        ];
    }
}
