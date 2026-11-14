<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * ログアウト後の遷移先（管理者は管理者ログイン画面、一般ユーザーはログイン画面）
 */
class LogoutResponse implements LogoutResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return redirect($request->is('admin/*') ? '/admin/login' : '/login');
    }
}
