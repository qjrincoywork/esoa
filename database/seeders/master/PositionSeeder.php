<?php

namespace Database\Seeders\Master;

use Illuminate\Database\Seeder;
use App\Models\Position;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            'Not Available',
            'RANK & FILE',
            'SUPERVISOR',
            'MANAGER',
            'EXECUTIVE',
        ];

        foreach ($positions as $name) {
            Position::firstOrCreate(['name' => $name, 'description' => $name]);
        }
    }
}
