<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * ログイン済みのユーザーがログイン画面などを開いた場合、ロールに応じたトップ画面へ移す
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $user = collect(empty($guards) ? [null] : $guards)
            ->map(fn (?string $guard) => Auth::guard($guard)->user())
            ->filter()
            ->first();

        if (! is_null($user)) {
            return redirect($user->admin_status ? '/admin/attendance/list' : RouteServiceProvider::HOME);
        }

        return $next($request);
    }
}
