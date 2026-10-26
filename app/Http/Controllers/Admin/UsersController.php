<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\ToggleModuleRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
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

        $message = 'Користувача створено.';
        if (! $request->filled('password')) {
            $message = $this->usersService->sendInvitation($user)
                ? 'Користувача створено. Запрошення надіслано на email.'
                : 'Користувача створено, але лист із запрошенням не вдалося надіслати. Спробуйте надіслати його повторно.';
        }

        return $this->present($user->load('organization', 'roles', 'modules'), UserForAdminPresenter::class, [], $message);
    }

    public function approve(string $identifier)
    {
        $user = $this->usersService->findByIdentifierOrFail($identifier);
        $emailSent = $this->usersService->approve($user);

        return $this->present($user, UserForAdminPresenter::class, [], $emailSent
            ? 'Доступ підтверджено. Користувачу надіслано лист.'
            : 'Доступ підтверджено, але лист користувачу не вдалося надіслати.');
    }

    public function reject(string $identifier)
    {
        $user = $this->usersService->findByIdentifierOrFail($identifier);
        $this->usersService->reject($user);

        return $this->present($user, UserForAdminPresenter::class, [], 'Заявку відхилено.');
    }

    public function invite(string $identifier)
    {
        $user = $this->usersService->findByIdentifierOrFail($identifier);

        if (! $this->usersService->sendInvitation($user)) {
            return $this->serverErrorResponse('Не вдалося надіслати лист. Перевірте налаштування пошти.');
        }

        return $this->successResponse('Запрошення надіслано на '.$user->email.'.');
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
