<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::DEFINITIONS as $slug => [$ar, $en]) {
            Role::updateOrCreate(['slug' => $slug], ['name_ar' => $ar, 'name_en' => $en]);
        }
    }
}
