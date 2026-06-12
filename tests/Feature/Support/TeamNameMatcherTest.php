<?php

namespace Tests\Feature\Support;

use App\Support\Teams\TeamNameMatcher;
use Tests\TestCase;

class TeamNameMatcherTest extends TestCase
{
    public function test_catalog_covers_all_48_world_cup_teams_from_both_providers(): void
    {
        $pairs = [
            'ALG' => ['Algeria', 'Algeria'],
            'ARG' => ['Argentina', 'Argentina'],
            'AUS' => ['Australia', 'Australia'],
            'AUT' => ['Austria', 'Austria'],
            'BEL' => ['Belgium', 'Belgium'],
            'BIH' => ['Bosnia-Herzegovina', 'Bosnia & Herzegovina'],
            'BRA' => ['Brazil', 'Brazil'],
            'CAN' => ['Canada', 'Canada'],
            'CPV' => ['Cape Verde Islands', 'Cape Verde Islands'],
            'COL' => ['Colombia', 'Colombia'],
            'COD' => ['Congo DR', 'Congo DR'],
            'CRO' => ['Croatia', 'Croatia'],
            'CUW' => ['Curaçao', 'Curaçao'],
            'CZE' => ['Czechia', 'Czech Republic'],
            'ECU' => ['Ecuador', 'Ecuador'],
            'EGY' => ['Egypt', 'Egypt'],
            'ENG' => ['England', 'England'],
            'FRA' => ['France', 'France'],
            'GER' => ['Germany', 'Germany'],
            'GHA' => ['Ghana', 'Ghana'],
            'HAI' => ['Haiti', 'Haiti'],
            'IRN' => ['Iran', 'Iran'],
            'IRQ' => ['Iraq', 'Iraq'],
            'CIV' => ['Ivory Coast', 'Ivory Coast'],
            'JPN' => ['Japan', 'Japan'],
            'JOR' => ['Jordan', 'Jordan'],
            'MEX' => ['Mexico', 'Mexico'],
            'MAR' => ['Morocco', 'Morocco'],
            'NED' => ['Netherlands', 'Netherlands'],
            'NZL' => ['New Zealand', 'New Zealand'],
            'NOR' => ['Norway', 'Norway'],
            'PAN' => ['Panama', 'Panama'],
            'PAR' => ['Paraguay', 'Paraguay'],
            'POR' => ['Portugal', 'Portugal'],
            'QAT' => ['Qatar', 'Qatar'],
            'KSA' => ['Saudi Arabia', 'Saudi Arabia'],
            'SCO' => ['Scotland', 'Scotland'],
            'SEN' => ['Senegal', 'Senegal'],
            'RSA' => ['South Africa', 'South Africa'],
            'KOR' => ['South Korea', 'South Korea'],
            'ESP' => ['Spain', 'Spain'],
            'SWE' => ['Sweden', 'Sweden'],
            'SUI' => ['Switzerland', 'Switzerland'],
            'TUN' => ['Tunisia', 'Tunisia'],
            'TUR' => ['Turkey', 'Türkiye'],
            'USA' => ['United States', 'USA'],
            'URU' => ['Uruguay', 'Uruguay'],
            'UZB' => ['Uzbekistan', 'Uzbekistan'],
        ];

        $this->assertCount(48, TeamNameMatcher::worldCupAliases());
        $this->assertCount(48, $pairs);

        foreach ($pairs as $key => [$footballDataName, $apiFootballName]) {
            $this->assertSame($key, TeamNameMatcher::aliasKey($footballDataName));
            $this->assertSame($key, TeamNameMatcher::aliasKey($apiFootballName));
            $this->assertTrue(
                TeamNameMatcher::matches($footballDataName, $apiFootballName),
                "{$footballDataName} deveria corresponder a {$apiFootballName}"
            );
        }
    }

    public function test_provider_specific_codes_resolve_to_same_country(): void
    {
        $this->assertTrue(TeamNameMatcher::matches('COD', 'CGO'));
        $this->assertTrue(TeamNameMatcher::matches('CUW', 'CUR'));
        $this->assertTrue(TeamNameMatcher::matches('URY', 'URU'));
        $this->assertTrue(TeamNameMatcher::matches('Korea Republic', 'South Korea'));
    }
}
