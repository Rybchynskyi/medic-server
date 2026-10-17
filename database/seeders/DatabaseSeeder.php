<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            ModulesSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
