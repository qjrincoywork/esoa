<?php

namespace Database\Seeders\Master;

use Illuminate\Database\Seeder;
use App\Models\CivilStatus;

class CivilStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'Not Applicable',
            'Single',
            'Married',
            'Defacto',
            'Widowed',
            'Separated',
            'Single Parent',
            'Common Law Partner',
            'Divorced',
            'Annulled',
        ];

        foreach ($statuses as $name) {
            CivilStatus::firstOrCreate(['name' => $name, 'description' => $name]);
        }
    }
}
