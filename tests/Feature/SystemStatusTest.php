<?php

namespace Tests\Feature;

use App\Domain\System\SystemStatus;
use App\Jobs\QueueHeartbeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    public function test_nothing_seen_means_stopped_and_heartbeats_turn_it_green(): void
    {
        config(['queue.default' => 'database']);
        $status = app(SystemStatus::class);
        $this->assertFalse($status->snapshot()['scheduler']['ok']);
        $this->assertFalse($status->snapshot()['worker']['ok']);
        $this->assertSame(2, $status->problems());

        Queue::fake();
        SystemStatus::tick();
        Queue::assertPushed(QueueHeartbeat::class);
        $this->assertTrue($status->snapshot()['scheduler']['ok']);

        (new QueueHeartbeat)->handle();                       // a worker ran it
        $this->assertTrue($status->snapshot()['worker']['ok']);
        $this->assertSame(0, $status->problems());

        $this->travel(SystemStatus::STALE_MINUTES + 1)->minutes();
        $this->assertFalse($status->snapshot()['worker']['ok'], 'no heartbeat for minutes = stopped');
    }

    public function test_admin_sees_the_page_and_failed_jobs_clients_do_not(): void
    {
        \Illuminate\Support\Facades\DB::table('failed_jobs')->insert(['uuid' => 'u-1', 'connection' => 'database', 'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\SendBatchPdfToTelegram']), 'exception' => "RuntimeException: Telegram is down\n#0 stack", 'failed_at' => now()]);

        $this->actingAs($this->admin());
        $this->get(route('admin.system'))->assertOk()->assertSee(__('system.title'))->assertSee('SendBatchPdfToTelegram')
            ->assertSee('RuntimeException: Telegram is down')->assertDontSee('#0 stack');

        $this->actingAs(User::create(['name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]));
        $this->get(route('admin.system'))->assertForbidden();
    }
}
