<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse;

/**
 * 認証メールの再送後のレスポンス（提供 Blade が session('message') を表示するため、そのキーで返す）
 */
class VerificationLinkSentResponse implements EmailVerificationNotificationSentResponse
{
    /**
     * メール認証誘導画面へ戻し、再送したことを伝える
     *
     * 引数の型はインターフェース（Responsable::toResponse）が宣言していないため、
     * 実装側で Request 型を付けると互換性エラーになる。型は PHPDoc で示す
     *
     * @param  Request  $request
     * @return RedirectResponse メール認証誘導画面へのリダイレクト
     */
    public function toResponse($request): RedirectResponse
    {
        return back()->with('message', '認証メールを再送しました。');
    }
}
