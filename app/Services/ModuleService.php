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

    public function toggleForUser(User $user, string $moduleKey, bool $enabled): void
    {
        $module = $this->findByKeyOrFail($moduleKey);

        $user->modules()->syncWithoutDetaching([
            $module->id => ['enabled' => $enabled],
        ]);
    }
}
