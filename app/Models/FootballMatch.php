<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FootballMatch extends Model
{
    protected $fillable = [
        'provider','external_id','competition_id','competition_season_id','home_team_id','away_team_id','utc_date','local_date',
        'status','matchday','stage','group_name','score_winner','score_duration','home_score_full_time','away_score_full_time',
        'home_score_half_time','away_score_half_time','home_score_extra_time','away_score_extra_time','home_score_penalties','away_score_penalties',
        'last_updated_by_provider_at','in_play_started_at','interval_started_at','resumed_from_interval_at','finished_at',
        'live_clock_anchor_at','live_clock_accumulated_seconds','raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'utc_date' => 'datetime',
            'local_date' => 'datetime',
            'last_updated_by_provider_at' => 'datetime',
            'in_play_started_at' => 'datetime',
            'interval_started_at' => 'datetime',
            'resumed_from_interval_at' => 'datetime',
            'finished_at' => 'datetime',
            'live_clock_anchor_at' => 'datetime',
            'live_clock_accumulated_seconds' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(CompetitionSeason::class, 'competition_season_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(FootballMatchDetail::class, 'football_match_id');
    }

    public function isFinished(): bool
    {
        return $this->status === 'FINISHED';
    }

    public function predictionLockTimeFor(Pool $pool): Carbon
    {
        $utcKickoff = $this->utcDateAsUtc() ?? $this->utc_date?->copy()->utc();

        if (! $utcKickoff) {
            return now()->utc();
        }

        return $utcKickoff->subMinutes($pool->predictionLockMinutes());
    }

    public function isPredictionLockedFor(Pool $pool): bool
    {
        return now()->utc()->greaterThanOrEqualTo($this->predictionLockTimeFor($pool)) || $this->isFinished();
    }

    public function kickoffAtBrazil(): ?Carbon
    {
        if ($utcKickoff = $this->utcDateAsUtc()) {
            return $utcKickoff->copy()->timezone('America/Sao_Paulo');
        }

        if ($this->local_date) {
            // local_date may be stored as Sao Paulo wall-clock time.
            return Carbon::parse($this->local_date->format('Y-m-d H:i:s'), 'America/Sao_Paulo');
        }

        return null;
    }

    private function utcDateAsUtc(): ?Carbon
    {
        $rawUtcDate = $this->getRawOriginal('utc_date');
        if (! $rawUtcDate) {
            return null;
        }

        // utc_date é salvo sem timezone no banco; aqui forçamos interpretação UTC
        // para evitar deslocamento duplo ao converter para America/Sao_Paulo.
        return Carbon::parse((string) $rawUtcDate, 'UTC');
    }
}
