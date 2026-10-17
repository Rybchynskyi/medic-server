<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
}
