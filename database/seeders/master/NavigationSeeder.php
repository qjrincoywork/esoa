<?php

namespace Database\Seeders\master;

use Illuminate\Database\Seeder;
use App\Models\Navigation;

class NavigationSeeder extends Seeder
{
    public function run()
    {
        Navigation::firstOrCreate(['id' => 1], [
            'name' => 'ICT Admin',
            'label' => 'Admin',
            'icon' => 'LayoutGrid',
            'created_by' => 1,
            'status' => 1,
            'order_number' => 1,
        ]);

        Navigation::firstOrCreate(['id' => 2], [
            'name' => 'Soa',
            'label' => 'Soa',
            'icon' => 'File',
            'created_by' => 1,
            'status' => 1,
            'order_number' => 2,
        ]);
    }
}

