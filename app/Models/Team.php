<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = ['provider', 'external_id', 'name', 'short_name', 'tla', 'crest'];

    public function getLocalizedNameAttribute(): string
    {
        $raw = $this->short_name ?: $this->name ?: 'Equipe';

        $map = [
            'Mexico' => 'México',
            'South Africa' => 'África do Sul',
            'Korea Republic' => 'Coreia do Sul',
            'Czechia' => 'Tchéquia',
            'Canada' => 'Canadá',
            'Bosnia-H.' => 'Bósnia e Herzegovina',
            'USA' => 'Estados Unidos',
            'Paraguay' => 'Paraguai',
            'Qatar' => 'Catar',
            'Switzerland' => 'Suíça',
            'Brazil' => 'Brasil',
            'Morocco' => 'Marrocos',
            'Haiti' => 'Haiti',
            'Scotland' => 'Escócia',
            'Australia' => 'Austrália',
            'Turkey' => 'Turquia',
            'Germany' => 'Alemanha',
            'Netherlands' => 'Países Baixos',
            'Japan' => 'Japão',
            'Ivory Coast' => 'Costa do Marfim',
            'Ecuador' => 'Equador',
            'Sweden' => 'Suécia',
            'Tunisia' => 'Tunísia',
            'Spain' => 'Espanha',
            'Cape Verde' => 'Cabo Verde',
            'Belgium' => 'Bélgica',
            'Egypt' => 'Egito',
            'Saudi Arabia' => 'Arábia Saudita',
            'Uruguay' => 'Uruguai',
            'Iran' => 'Irã',
            'New Zealand' => 'Nova Zelândia',
            'France' => 'França',
            'Senegal' => 'Senegal',
            'Iraq' => 'Iraque',
            'Norway' => 'Noruega',
            'Algeria' => 'Argélia',
            'Austria' => 'Áustria',
            'Jordan' => 'Jordânia',
            'Congo DR' => 'Congo (RD)',
            'England' => 'Inglaterra',
            'Croatia' => 'Croácia',
            'Ghana' => 'Gana',
            'Panama' => 'Panamá',
            'Uzbekistan' => 'Uzbequistão',
            'Colombia' => 'Colômbia',
        ];

        return $map[$raw] ?? $raw;
    }

    public function homeMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'away_team_id');
    }

    public function standingRows(): HasMany
    {
        return $this->hasMany(StandingRow::class);
    }

    public function providerRefs(): HasMany
    {
        return $this->hasMany(TeamProviderRef::class);
    }
}
