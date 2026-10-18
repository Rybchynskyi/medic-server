<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Presenters\UserPresenter;
use App\Services\UsersService;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService
    ) {}

    public function register(RegisterRequest $request)
    {
        $user = $this->usersService->register($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return $this->present($user->load('organization', 'roles', 'modules'), UserPresenter::class, [], 'Реєстрація успішна. Ласкаво просимо!');
    }
}
