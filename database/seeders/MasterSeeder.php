<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Master\RolePermissionSeeder;
use Database\Seeders\Master\NavigationSeeder;
use Database\Seeders\Master\NavigationModuleSeeder;
use Illuminate\Support\Facades\DB;

class MasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->call([
                RolePermissionSeeder::class,
                NavigationSeeder::class,
                NavigationModuleSeeder::class,
            ]);
            // Commit transaction
            DB::commit();
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();
            throw $e;
        }
    }
}
