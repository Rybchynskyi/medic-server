<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\ToggleModuleRequest;
use App\Presenters\UserForAdminPresenter;
use App\Services\UsersService;

class UsersController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService
    ) {}

    public function index()
    {
        return $this->present($this->usersService->allWithRelations(), UserForAdminPresenter::class);
    }

    public function store(CreateUserRequest $request)
    {
        $user = $this->usersService->create($request->validated());

        return $this->present($user->load('organization', 'roles', 'modules'), UserForAdminPresenter::class, [], 'Користувача створено.');
    }

    public function update(UpdateUserRequest $request, string $identifier)
    {
        $user = $this->usersService->findByIdentifierOrFail($identifier);
        $user = $this->usersService->update($user, $request->validated());

        return $this->present($user->load('organization', 'roles', 'modules'), UserForAdminPresenter::class, [], 'Користувача оновлено.');
    }

    public function destroy(string $identifier)
    {
        $user = $this->usersService->findByIdentifierOrFail($identifier);
        $this->usersService->delete($user);

        return $this->successResponse('Користувача видалено.');
    }

    public function toggleModule(ToggleModuleRequest $request, string $identifier, string $moduleKey)
    {
        $user = $this->usersService->findByIdentifierOrFail($identifier);
        $user = $this->usersService->toggleModule($user, $moduleKey, $request->boolean('enabled'));

        return $this->present($user->load('organization', 'roles', 'modules'), UserForAdminPresenter::class, [], 'Модуль оновлено.');
    }
}
