<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MonitorVendorHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vendor:health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor external vendor APIs like RajaOngkir and Tripay for latency.';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\Alerting\SystemAlertService $alertService)
    {
        $this->checkRajaOngkir($alertService);
        $this->checkTripay($alertService);
        $this->info('Vendor health check completed.');
    }

    protected function checkRajaOngkir(\App\Services\Alerting\SystemAlertService $alertService)
    {
        $start = microtime(true);
        try {
            // Kita coba endpoint yang ringan, misal get province
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders(['key' => config('services.rajaongkir.key')])
                ->get('https://api.rajaongkir.com/starter/province');

            $latency = microtime(true) - $start;

            if ($response->failed()) {
                $alertService->dispatch('RajaOngkir API Down', 'critical', ['status' => $response->status()]);
            } elseif ($latency > 5) {
                $alertService->dispatch('RajaOngkir Latency Spike', 'critical', ['latency_seconds' => round($latency, 2)]);
            } elseif ($latency > 2) {
                $alertService->dispatch('RajaOngkir Slow Response', 'warning', ['latency_seconds' => round($latency, 2)]);
            }
        } catch (\Exception $e) {
            $alertService->dispatch('RajaOngkir Connection Failed', 'critical', ['error' => $e->getMessage()]);
        }
    }

    protected function checkTripay(\App\Services\Alerting\SystemAlertService $alertService)
    {
        $start = microtime(true);
        try {
            // Hit endpoint status Tripay
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withToken(config('services.tripay.api_key'))
                ->get(config('services.tripay.api_url') . 'merchant/payment-channel');

            $latency = microtime(true) - $start;

            if ($response->failed()) {
                $alertService->dispatch('Tripay API Down', 'critical', ['status' => $response->status()]);
            } elseif ($latency > 5) {
                $alertService->dispatch('Tripay Latency Spike', 'critical', ['latency_seconds' => round($latency, 2)]);
            } elseif ($latency > 3) {
                $alertService->dispatch('Tripay Slow Response', 'warning', ['latency_seconds' => round($latency, 2)]);
            }
        } catch (\Exception $e) {
            $alertService->dispatch('Tripay Connection Failed', 'critical', ['error' => $e->getMessage()]);
        }
    }
}
