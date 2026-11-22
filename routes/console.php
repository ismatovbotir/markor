<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every minute: load queued CSV batches and check their products; keeps polling ~55 s so uploads start within seconds.
\Illuminate\Support\Facades\Schedule::command('batches:process --for=55')->everyMinute()->withoutOverlapping(5);

// Local copies of label PDFs that were not archived to Telegram are kept 7 days.
\Illuminate\Support\Facades\Schedule::command('pdfs:prune --days=7')->dailyAt('03:30');

// visit statistics: keep 180 days of daily aggregates
\Illuminate\Support\Facades\Schedule::call(fn () => \App\Domain\Stats\VisitCounter::prune())->name('visits:prune')->dailyAt('03:40');

// Asl Belgisi batch checks no queue worker took, and stuck ones (settled, unchecked codes refunded).
\Illuminate\Support\Facades\Schedule::command('aslbelgi:checks')->everyMinute()->withoutOverlapping(30);

// Telegram reminder to the company owner: Asl Belgisi API key expires in 14 days, in 3 days, expired (each once).
\Illuminate\Support\Facades\Schedule::command('aslbelgi:key-reminders')->dailyAt('09:00');
