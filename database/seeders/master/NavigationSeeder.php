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
        ]);

        Navigation::firstOrCreate(['id' => 2], [
            'name' => 'User Dashboard',
            'label' => 'Users',
            'icon' => 'Users',
            'created_by' => 1,
            'status' => 1,
        ]);
    }
}

