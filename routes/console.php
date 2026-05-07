<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('Bolao 2026 pronto para sincronizacao.');
})->purpose('Display an informational message');

Schedule::command('worldcup:sync-group-stage')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('worldcup:sync-match-details --limit='.config('football-data.match_details.sync_limit_per_minute', 8))
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
