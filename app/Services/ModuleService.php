<?php

namespace App\Services;

use App\Models\Module;
use App\Models\User;
use App\Repositories\ModuleRepository;
use Illuminate\Database\Eloquent\Collection;

class ModuleService extends CoreService
{
    public function __construct(
        private readonly ModuleRepository $moduleRepository
    ) {}

    public function all(): Collection
    {
        return $this->moduleRepository->all();
    }

    public function findByKeyOrFail(string $key): Module
    {
        $module = $this->moduleRepository->findByKey($key);

        if (! $module) {
            throw new \RuntimeException("Module [{$key}] not found.");
        }

        return $module;
    }

    public function setEnabledForNewOrganizations(string $moduleKey, bool $enabled): Module
    {
        $module = $this->findByKeyOrFail($moduleKey);
        $module->enabled_for_new_organizations = $enabled;
        $this->moduleRepository->save($module);

        return $module;
    }

    public function enableDefaultsForUser(User $user): void
    {
        $modules = $this->moduleRepository->enabledForNewOrganizations();

        $user->modules()->syncWithoutDetaching(
            $modules->mapWithKeys(fn (Module $module) => [$module->id => ['enabled' => true]])->all()
        );
    }

    public function toggleForUser(User $user, string $moduleKey, bool $enabled): void
    {
        $module = $this->findByKeyOrFail($moduleKey);

        $user->modules()->syncWithoutDetaching([
            $module->id => ['enabled' => $enabled],
        ]);
    }
}
