<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\UsersService;

class RegisterController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService
    ) {}

    public function register(RegisterRequest $request)
    {
        // Not logged in: the account stays pending until an admin approves it.
        $this->usersService->register($request->validated());

        return $this->successResponse('Заявку надіслано. Ми повідомимо Вас на email, щойно адміністратор підтвердить доступ.');
    }
}
