<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatuses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Presenters\UserPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(LoginRequest $request)
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Невірний email або пароль.'], 401);
        }

        // Checked after the password so the status isn't revealed to someone who doesn't know it.
        $user = Auth::user();
        if (! $user->isActive()) {
            Auth::guard('web')->logout();

            return $this->forbiddenResponse($user->status === UserStatuses::PENDING
                ? 'Ваша заявка ще очікує підтвердження адміністратором.'
                : 'Доступ до системи для цього облікового запису закрито.');
        }

        $request->session()->regenerate();

        return $this->present(Auth::user()->load('organization', 'roles', 'modules'), UserPresenter::class);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->successResponse('Ви вийшли із системи.');
    }

    public function user(Request $request)
    {
        return $this->present($request->user()->load('organization', 'roles', 'modules'), UserPresenter::class);
    }
}
