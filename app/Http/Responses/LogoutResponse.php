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
     * ログアウト後のリダイレクトを返す
     *
     * 引数の型はインターフェース（Responsable::toResponse）が宣言していないため、
     * 実装側で Request 型を付けると互換性エラーになる。型は PHPDoc で示す
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return redirect($request->is('admin/*') ? '/admin/login' : '/login');
    }
}
