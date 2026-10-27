<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FundType;
use App\Models\Fund;
use Illuminate\Database\Seeder;

/**
 * The NGO's starting funds. Safe to re-run: matches on code and only fills in
 * missing funds — it never changes a fund that already exists.
 */
class FundsSeeder extends Seeder
{
    public function run(): void
    {
        $funds = [
            [
                'code' => 'ZAKAT',
                'name' => 'Zakat',
                'type' => FundType::Zakat,
                'sort_order' => 10,
                'description' => 'Obligatory alms. Spent only on Zakat-eligible beneficiaries.',
            ],
            [
                'code' => 'SADQA',
                'name' => 'Sadqa / Atiya',
                'type' => FundType::Sadqa,
                'sort_order' => 20,
                'description' => 'Voluntary charity for general welfare.',
            ],
            [
                'code' => 'FITRANA',
                'name' => 'Fitrana',
                'type' => FundType::Fitrana,
                'sort_order' => 30,
                'description' => 'Sadaqat-ul-Fitr, collected before Eid-ul-Fitr.',
            ],
            [
                'code' => 'FIDYA',
                'name' => 'Fidya',
                'type' => FundType::Fidya,
                'sort_order' => 40,
                'description' => 'Compensation for missed fasts, used to feed the needy.',
            ],
            [
                'code' => 'GENERAL',
                'name' => 'General Fund',
                'type' => FundType::General,
                'sort_order' => 50,
                'description' => 'Unrestricted donations, including running costs.',
            ],
            [
                'code' => 'QURBANI',
                'name' => 'Qurbani',
                'type' => FundType::Restricted,
                'sort_order' => 60,
                'restriction' => 'Qurbani animals, slaughter and meat distribution only.',
                'description' => 'Collected for Eid-ul-Adha Qurbani.',
            ],
        ];

        foreach ($funds as $fund) {
            Fund::withTrashed()->firstOrCreate(['code' => $fund['code']], $fund);
        }
    }
}
