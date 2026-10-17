<?php

namespace App\Http\Controllers\Auth;

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
