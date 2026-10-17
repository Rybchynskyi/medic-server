<?php

namespace Database\Seeders;

use App\Enums\ModuleKeys;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        Module::updateOrCreate(
            ['key' => ModuleKeys::MATERIALS_CALCULATOR],
            ['name' => ModuleKeys::getTitle(ModuleKeys::MATERIALS_CALCULATOR)]
        );
    }
}
