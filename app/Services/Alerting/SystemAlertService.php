<?php

namespace App\Services\Alerting;

use App\Models\SystemAlertLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SystemAlertService
{
    /**
     * Dispatch an alert to the system.
     */
    public function dispatch(string $event, string $severity, array $payload = []): void
    {
        // 1. Alert Fatigue Prevention (Cooldown)
        // Jika event yang sama dengan severity yang sama sudah ditembak dalam 30 menit terakhir, jangan kirim webhook atau tulis log berulang.
        $cacheKey = "alert_cooldown_{$event}_{$severity}";
        if (Cache::has($cacheKey)) {
            Log::info("SystemAlert: Alert {$event} suppressed due to cooldown.");
            return;
        }

        // Lock for 30 minutes to prevent alert fatigue
        Cache::put($cacheKey, true, now()->addMinutes(30));

        // 2. Log to Database (Persistent Audit Trail)
        $log = SystemAlertLog::create([
            'event' => $event,
            'severity' => $severity,
            'payload' => $payload,
            'status' => 'open',
        ]);

        // 3. Send Webhook to Telegram / Discord
        $this->sendWebhook($log);
    }

    /**
     * Send webhook notification (e.g., Telegram).
     */
    protected function sendWebhook(SystemAlertLog $log): void
    {
        $webhookUrl = config('services.telegram.webhook_url');

        if (!$webhookUrl) {
            Log::warning("SystemAlert: Telegram webhook URL not configured.");
            return;
        }

        $emoji = match ($log->severity) {
            'info' => 'ℹ️',
            'warning' => '⚠️',
            'critical' => '🚨',
            'emergency' => '🔥',
            default => '🔔',
        };

        $message = "{$emoji} <b>[{$log->severity}] {$log->event}</b>\n";
        $message .= "ID: {$log->id}\n";
        if (!empty($log->payload)) {
            $message .= "<pre>" . json_encode($log->payload, JSON_PRETTY_PRINT) . "</pre>";
        }

        try {
            Http::timeout(3)->post($webhookUrl, [
                'chat_id' => config('services.telegram.chat_id'),
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $e) {
            Log::error("SystemAlert: Failed to send webhook. " . $e->getMessage());
        }
    }
}
