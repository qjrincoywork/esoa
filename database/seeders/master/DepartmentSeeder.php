<?php

namespace Database\Seeders\Master;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'Not Applicable',
            'INTERNAL AUDIT',
            'ACCUSA',
            'BILLING',
            'CLAIMS ADMINISTRATION',
            'EXECUTIVE',
            'HR',
            'ICT',
            'CUSTOMER CARE',
            'PNUM',
            'MDA',
            'SALES-RENEWAL',
            'SALES-NEW BUSINESS I',
            'SALES-NEW BUSINESS II',
            'FMD',
            'ACCOUNTING',
            'ACAUM',
        ];

        foreach ($departments as $name) {
            Department::firstOrCreate(['name' => $name, 'description' => $name]);
        }
    }
}
