<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * ログイン後の遷移先（管理者は勤怠一覧、一般ユーザーは勤怠登録画面）
 */
class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        if ($request->user()->admin_status) {
            return redirect('/admin/attendance/list');
        }

        return redirect()->intended('/attendance');
    }
}
