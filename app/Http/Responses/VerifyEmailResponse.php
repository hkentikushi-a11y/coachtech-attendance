<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;

/**
 * メール認証の完了後のレスポンス
 *
 * Fortify は認証前に開こうとしていたページへ戻すが、要件（FN011）どおり常に勤怠登録画面へ移す
 */
class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /**
     * 勤怠登録画面へリダイレクトする
     *
     * 引数の型はインターフェース（Responsable::toResponse）が宣言していないため、
     * 実装側で Request 型を付けると互換性エラーになる。型は PHPDoc で示す
     *
     * @param  Request  $request
     * @return RedirectResponse 勤怠登録画面へのリダイレクト
     */
    public function toResponse($request): RedirectResponse
    {
        $request->session()->forget('url.intended');

        return redirect('/attendance');
    }
}
