<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\Alerting\SystemAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SystemAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_deduplication()
    {
        Cache::flush();
        Http::fake(); // Mencegah benar-benar nge-hit Telegram

        $service = new SystemAlertService();

        // Tembak 100 kali
        for ($i = 0; $i < 100; $i++) {
            $service->dispatch('Tripay API Down', 'critical', ['error' => 'Timeout']);
        }

        // Harus hanya tercatat 1 di DB
        $count = DB::table('system_alert_logs')
            ->where('event', 'Tripay API Down')
            ->count();

        $this->assertEquals(1, $count, 'Sistem tidak meredam duplikasi alert (Deduplication gagal)');
    }
}
