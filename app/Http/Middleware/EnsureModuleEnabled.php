<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $user = $request->user();

        if (! $user || ! $user->organization_id || ! $user->hasModuleEnabled($moduleKey)) {
            return response()->json([
                'message' => 'Модуль недоступний. Зверніться до адміністратора.',
            ], 403);
        }

        return $next($request);
    }
}
