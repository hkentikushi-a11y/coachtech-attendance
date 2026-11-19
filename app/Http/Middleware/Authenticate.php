<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  Request  $request  リクエスト
     * @return ?string リダイレクト先（JSON を求めるリクエストは null）
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        return $request->is('admin/*', 'stamp_correction_request/approve/*') ? '/admin/login' : route('login');
    }
}
