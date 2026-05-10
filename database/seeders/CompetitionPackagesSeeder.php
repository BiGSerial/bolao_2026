<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionPackage;
use App\Models\CompetitionPackageItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompetitionPackagesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $packages = [
                'basic' => [
                    'name' => 'Plano Básico',
                    'description' => 'Acesso ao bolão da Copa do Mundo.',
                    'active' => true,
                    'competitions' => ['WC'],
                ],
                'tier2' => [
                    'name' => 'Plano Tier 2',
                    'description' => 'Acesso a Copa do Mundo e Campeonato Brasileiro.',
                    'active' => true,
                    'competitions' => ['WC', 'BSA'],
                ],
            ];

            foreach ($packages as $code => $data) {
                $package = CompetitionPackage::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $data['name'],
                        'description' => $data['description'],
                        'active' => (bool) $data['active'],
                    ]
                );

                $competitionCodes = array_values(array_unique(array_map('strtoupper', $data['competitions'])));

                CompetitionPackageItem::query()
                    ->where('competition_package_id', $package->id)
                    ->whereNotIn('competition_code', $competitionCodes)
                    ->delete();

                foreach ($competitionCodes as $competitionCode) {
                    $competitionId = Competition::query()
                        ->where('code', $competitionCode)
                        ->value('id');

                    CompetitionPackageItem::updateOrCreate(
                        [
                            'competition_package_id' => $package->id,
                            'competition_code' => $competitionCode,
                        ],
                        [
                            'competition_id' => $competitionId,
                        ]
                    );
                }
            }

            $basicId = CompetitionPackage::query()->where('code', 'basic')->value('id');
            $tier2Id = CompetitionPackage::query()->where('code', 'tier2')->value('id');

            if ($basicId) {
                User::query()
                    ->whereNull('competition_package_id')
                    ->update(['competition_package_id' => $basicId]);
            }

            if ($tier2Id) {
                User::query()
                    ->where('is_admin', true)
                    ->update(['competition_package_id' => $tier2Id]);
            }
        });
    }
}
