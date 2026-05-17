<?php

declare(strict_types=1);

use App\Services\ApiFootball\ApiFootballClient;
use App\Services\FootballData\FootballDataClient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fd = app(FootballDataClient::class);
$af = app(ApiFootballClient::class);

$code = 'BSA';
$season = 2026;
$stage = 'REGULAR_SEASON';
$leagueId = 71;

$fdPayload = $fd->competitionMatches($code, $season, $stage);
$fdMatches = (array) ($fdPayload['matches'] ?? []);

$byDate = [];
foreach ($fdMatches as $m) {
    $utc = (string) ($m['utcDate'] ?? '');
    $date = substr($utc, 0, 10);
    if ($date === '') {
        continue;
    }

    $byDate[$date][] = [
        'utc' => $utc,
        'home' => (string) data_get($m, 'homeTeam.name', ''),
        'away' => (string) data_get($m, 'awayTeam.name', ''),
    ];
}

ksort($byDate);

$normalize = static fn (string $v): string => trim(preg_replace('/\s+/', ' ', Str::upper(Str::ascii($v))) ?? '');

foreach ($byDate as $date => $rows) {
    echo "=== {$date} ===".PHP_EOL;
    $afPayload = $af->fixturesByDate($leagueId, $season, $date, 'UTC');
    $afRows = (array) ($afPayload['response'] ?? []);

    echo 'FD: '.count($rows).' jogo(s)'.PHP_EOL;
    foreach ($rows as $r) {
        echo 'FD | '.$r['utc'].' | '.$r['home'].' x '.$r['away'].PHP_EOL;
    }

    echo 'AF: '.count($afRows).' jogo(s)'.PHP_EOL;
    foreach ($afRows as $f) {
        echo 'AF | '.(string) data_get($f, 'fixture.date', '').' | '
            .(string) data_get($f, 'teams.home.name', '').' x '
            .(string) data_get($f, 'teams.away.name', '').PHP_EOL;
    }

    echo '-- POSSIVEIS DIVERGENCIAS DE NOME --'.PHP_EOL;
    foreach ($rows as $r) {
        $fdPair = $normalize($r['home']).' x '.$normalize($r['away']);
        $matched = false;

        foreach ($afRows as $f) {
            $afPair = $normalize((string) data_get($f, 'teams.home.name', '')).' x '.$normalize((string) data_get($f, 'teams.away.name', ''));
            if ($fdPair === $afPair) {
                $matched = true;
                break;
            }
        }

        if (! $matched) {
            echo 'DIFF | '.$fdPair.PHP_EOL;
        }
    }

    echo PHP_EOL;
}

