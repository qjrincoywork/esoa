<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Database\Seeders\Master\RolePermissionSeeder;
use Database\Seeders\Master\NavigationSeeder;
use Database\Seeders\Master\NavigationModuleSeeder;
use Database\Seeders\Master\CitizenshipSeeder;
use Database\Seeders\Master\CivilStatusSeeder;
use Database\Seeders\Master\DepartmentSeeder;
use Database\Seeders\Master\PositionSeeder;

class MasterSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->call([
                RolePermissionSeeder::class,
                NavigationSeeder::class,
                NavigationModuleSeeder::class,
                CitizenshipSeeder::class,
                CivilStatusSeeder::class,
                DepartmentSeeder::class,
                PositionSeeder::class,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
