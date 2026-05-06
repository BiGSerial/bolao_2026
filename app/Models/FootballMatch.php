<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FootballMatch extends Model
{
    protected $fillable = [
        'provider','external_id','competition_id','competition_season_id','home_team_id','away_team_id','utc_date','local_date',
        'status','matchday','stage','group_name','score_winner','score_duration','home_score_full_time','away_score_full_time',
        'home_score_half_time','away_score_half_time','home_score_extra_time','away_score_extra_time','home_score_penalties','away_score_penalties',
        'last_updated_by_provider_at','raw_payload',
    ];

    protected function casts(): array
    {
        return ['utc_date' => 'datetime', 'local_date' => 'datetime', 'last_updated_by_provider_at' => 'datetime', 'raw_payload' => 'array'];
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

    public function isFinished(): bool
    {
        return $this->status === 'FINISHED';
    }

    public function predictionLockTimeFor(Pool $pool): Carbon
    {
        return $this->utc_date->copy()->subMinutes($pool->predictionLockMinutes());
    }

    public function isPredictionLockedFor(Pool $pool): bool
    {
        return now()->utc()->greaterThanOrEqualTo($this->predictionLockTimeFor($pool)) || $this->isFinished();
    }
}
