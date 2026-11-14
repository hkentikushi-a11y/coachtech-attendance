<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 一般ユーザー専用の画面を保護する。管理者は管理者の勤怠一覧へ移す
 */
class EnsureUserIsGeneral
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->admin_status) {
            return redirect('/admin/attendance/list');
        }

        return $next($request);
    }
}
