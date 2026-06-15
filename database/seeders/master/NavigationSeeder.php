<?php

namespace Database\Seeders\Master;

use Illuminate\Database\Seeder;
use App\Models\Navigation;

class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        $navigations = [
            [
                'name' => 'ICT Admin',
                'label' => 'Admin',
                'icon' => 'LayoutGrid',
                'created_by' => 1,
                'status' => 1,
                'order_number' => 1,
            ],
            [
                'name' => 'Soa',
                'label' => 'Soa',
                'icon' => 'File',
                'created_by' => 1,
                'status' => 1,
                'order_number' => 2,
            ],
        ];

        foreach ($navigations as $navigation) {
            Navigation::firstOrCreate(['name' => $navigation['name']], $navigation);
        }
    }
}
