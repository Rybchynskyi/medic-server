<?php

namespace Database\Seeders;

use App\Enums\RoleNames;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleNames::getValues() as $role) {
            Role::findOrCreate($role);
        }
    }
}
