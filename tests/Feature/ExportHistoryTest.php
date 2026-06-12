<?php

namespace Tests\Feature;

use App\Models\ExportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin Export history (.planning n/a — ad-hoc fix, follow-up to tracker #18/#23/#31
 * Export Lifecycle). admin.exports.index previously returned raw JSON with no UI and
 * no download link; admin.exports.download was unreachable due to a missing Storage
 * facade import. This covers both fixes without touching ExportOrdersJob's queue logic.
 */
class ExportHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->otherAdmin = User::factory()->create(['role' => 'admin']);
    }

    public function test_exports_index_renders_only_the_authenticated_users_jobs()
    {
        $own = ExportJob::create([
            'user_id' => $this->admin->id,
            'type' => 'orders',
            'status' => 'completed',
            'filters' => ['start_date' => null, 'end_date' => null, 'status' => null],
            'estimated_rows' => 10,
            'row_count' => 10,
            'file_path' => 'exports/orders_own.csv',
            'file_size_bytes' => 1234,
            'expires_at' => now()->addHours(24),
        ]);

        $other = ExportJob::create([
            'user_id' => $this->otherAdmin->id,
            'type' => 'orders',
            'status' => 'completed',
            'filters' => [],
            'estimated_rows' => 5,
            'row_count' => 5,
            'file_path' => 'exports/orders_other.csv',
            'file_size_bytes' => 567,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.exports.index'));

        $response->assertOk();
        $jobIds = array_column($response->viewData('page')['props']['jobs'], 'id');

        $this->assertContains($own->id, $jobIds);
        $this->assertNotContains($other->id, $jobIds);
    }

    public function test_download_returns_file_for_completed_non_expired_job()
    {
        Storage::fake('local');
        Storage::put('exports/orders_test.csv', "Order ID,Order Number\n1,ORD-1\n");

        $job = ExportJob::create([
            'user_id' => $this->admin->id,
            'type' => 'orders',
            'status' => 'completed',
            'filters' => [],
            'estimated_rows' => 1,
            'row_count' => 1,
            'file_path' => 'exports/orders_test.csv',
            'file_size_bytes' => Storage::size('exports/orders_test.csv'),
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.exports.download', $job->id));

        $response->assertOk();
        $response->assertDownload();
    }

    public function test_download_forbidden_for_another_users_job()
    {
        Storage::fake('local');
        Storage::put('exports/orders_other.csv', "Order ID\n1\n");

        $job = ExportJob::create([
            'user_id' => $this->otherAdmin->id,
            'type' => 'orders',
            'status' => 'completed',
            'filters' => [],
            'file_path' => 'exports/orders_other.csv',
            'file_size_bytes' => Storage::size('exports/orders_other.csv'),
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.exports.download', $job->id));

        $response->assertForbidden();
    }

    public function test_download_gone_for_expired_job()
    {
        Storage::fake('local');
        Storage::put('exports/orders_expired.csv', "Order ID\n1\n");

        $job = ExportJob::create([
            'user_id' => $this->admin->id,
            'type' => 'orders',
            'status' => 'completed',
            'filters' => [],
            'file_path' => 'exports/orders_expired.csv',
            'file_size_bytes' => Storage::size('exports/orders_expired.csv'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.exports.download', $job->id));

        $response->assertStatus(410);
    }

    public function test_download_not_found_when_job_not_completed()
    {
        $job = ExportJob::create([
            'user_id' => $this->admin->id,
            'type' => 'orders',
            'status' => 'processing',
            'filters' => [],
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.exports.download', $job->id));

        $response->assertStatus(404);
    }
}
