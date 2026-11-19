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
     * ログイン後のリダイレクトを返す
     *
     * 引数の型はインターフェース（Responsable::toResponse）が宣言していないため、
     * 実装側で Request 型を付けると互換性エラーになる。型は PHPDoc で示す
     *
     * @param  Request  $request
     * @return RedirectResponse 管理者は勤怠一覧、一般ユーザーは勤怠登録画面へのリダイレクト
     */
    public function toResponse($request): RedirectResponse
    {
        if ($request->user()->admin_status) {
            return redirect('/admin/attendance/list');
        }

        return redirect()->intended('/attendance');
    }
}
