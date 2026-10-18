<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateModuleRequest;
use App\Presenters\ModulePresenter;
use App\Services\ModuleService;

class ModulesController extends Controller
{
    public function __construct(
        private readonly ModuleService $moduleService
    ) {}

    public function index()
    {
        return $this->present($this->moduleService->all(), ModulePresenter::class);
    }

    public function update(UpdateModuleRequest $request, string $moduleKey)
    {
        $module = $this->moduleService->setEnabledForNewOrganizations(
            $moduleKey,
            $request->boolean('enabledForNewOrganizations')
        );

        return $this->present($module, ModulePresenter::class, [], 'Модуль оновлено.');
    }
}
