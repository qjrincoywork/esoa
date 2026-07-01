<?php

namespace Database\Seeders\Master;

use Illuminate\Database\Seeder;
use App\Models\Citizenship;

class CitizenshipSeeder extends Seeder
{
    public function run(): void
    {
        $citizenships = [
            'NA',
            'Filipino',
            'American',
            'Canadian',
            'Indian',
            'African',
            'Australian',
            'British',
            'Chinese',
            'Japanese',
            'Korean',
            'Singaporean',
            'Others',
        ];

        foreach ($citizenships as $name) {
            Citizenship::firstOrCreate(['name' => $name, 'description' => $name]);
        }
    }
}
