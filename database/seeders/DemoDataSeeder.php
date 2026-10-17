<?php

namespace Database\Seeders;

use App\Enums\ModuleKeys;
use App\Enums\RoleNames;
use App\Models\Organization;
use App\Models\User;
use App\Services\MaterialNormsImportService;
use App\Services\ModuleService;
use App\Services\ServicesImportService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@medic.test'],
            [
                'identifier' => substr(md5(uniqid((string) mt_rand())), 0, 12),
                'name' => 'Адміністратор',
                'password' => Hash::make('password'),
            ]
        );
        $admin->syncRoles([RoleNames::ADMIN]);

        $organization = Organization::firstOrCreate(
            ['name' => 'Медичний центр "Демо"'],
            ['identifier' => substr(md5(uniqid((string) mt_rand())), 0, 12)]
        );

        $user = User::firstOrCreate(
            ['email' => 'user@medic.test'],
            [
                'identifier' => substr(md5(uniqid((string) mt_rand())), 0, 12),
                'organization_id' => $organization->id,
                'name' => 'Демо користувач',
                'password' => Hash::make('password'),
            ]
        );
        $user->organization_id = $organization->id;
        $user->save();
        $user->syncRoles([RoleNames::USER]);

        app(ModuleService::class)->toggleForUser($user, ModuleKeys::MATERIALS_CALCULATOR, true);

        $servicesFile = database_path('seeders/data/perelik.xlsx');
        $normsFile = database_path('seeders/data/normy.xlsx');

        if (file_exists($servicesFile)) {
            app(ServicesImportService::class)->import(
                $organization,
                new UploadedFile($servicesFile, 'Перелік.xlsx', null, null, true)
            );
        }

        if (file_exists($normsFile)) {
            app(MaterialNormsImportService::class)->import(
                $organization,
                new UploadedFile($normsFile, 'Норми витрат матеріалів.xlsx', null, null, true)
            );
        }
    }
}
