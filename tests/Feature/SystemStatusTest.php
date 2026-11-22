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

    public function test_cron_only_servers_get_a_queue_worker_from_the_scheduler(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $worker = $events->first(fn ($e) => str_contains((string) $e->command, 'queue:work') && str_contains((string) $e->command, '--stop-when-empty'));
        $this->assertNotNull($worker, 'the scheduler starts a queue worker');
        $this->assertSame('* * * * *', $worker->expression);
        $this->assertTrue($events->contains(fn ($e) => $e->description === 'system:heartbeat'));
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

    public function test_details_window_shows_the_full_error_with_tokens_masked(): void
    {
        config(['telegram.token' => '123456789:AAFakeTokenForTestsOnly_abcdefghijklmnop']);
        $trace = "RuntimeException: Archiving PDF #1 to Telegram chat -5137212617 failed: Telegram 400: Bad Request: chat not found in /srv/app/Jobs/ArchiveLabelPdf.php:41\n"
            . "#0 https://api.telegram.org/bot123456789:AAFakeTokenForTestsOnly_abcdefghijklmnop/sendDocument\n#1 {main}";
        \Illuminate\Support\Facades\DB::table('failed_jobs')->insert(['uuid' => 'u-2', 'connection' => 'database', 'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ArchiveLabelPdf', 'maxTries' => 3, 'timeout' => 180]), 'exception' => $trace, 'failed_at' => now()]);
        $this->actingAs($this->admin());

        \Livewire\Livewire::test(\App\Http\Livewire\Admin\SystemPage::class)
            ->assertSee('Bad Request: chat not found')
            ->call('showDetail', 'u-2')
            ->assertSee('App\\Jobs\\ArchiveLabelPdf')->assertSee('#1 {main}')->assertSee('***:***')
            ->assertDontSee('AAFakeTokenForTestsOnly')
            ->call('closeDetail')->assertDontSee('#1 {main}');
    }

    public function test_archive_failure_keeps_telegrams_reason(): void
    {
        config(['telegram.token' => '123456789:AAFakeTokenForTestsOnly_abcdefghijklmnop']);
        \Illuminate\Support\Facades\Http::fake(['api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(
            ['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: group chat was upgraded to a supergroup chat', 'parameters' => ['migrate_to_chat_id' => -1001234567890]], 400)]);
        $api = app(\App\Domain\Telegram\TelegramApi::class);
        $this->assertNull($api->sendDocument('-5137212617', 'pdf', 'a.pdf'));
        $this->assertStringContainsString('upgraded to a supergroup', $api->lastError);
        $this->assertStringContainsString('-1001234567890', $api->lastError);
        $this->assertStringNotContainsString('AAFakeToken', $api->lastError);
    }
}
