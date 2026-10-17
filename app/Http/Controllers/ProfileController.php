<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Presenters\UserPresenter;
use App\Services\UsersService;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService
    ) {}

    public function update(UpdateProfileRequest $request)
    {
        $user = $this->usersService->updateOwnProfile($request->user(), $request->validated('name'));

        return $this->present($user->load('organization', 'roles', 'modules'), UserPresenter::class, [], 'Профіль оновлено.');
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $this->usersService->updateOwnPassword($request->user(), $request->validated('password'));

        return $this->successResponse('Пароль змінено.');
    }
}
