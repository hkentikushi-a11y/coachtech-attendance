<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * 公開 API（api/*）のエラーを API 仕様書の形式の JSON にする
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // ルートモデルバインディングで勤怠が見つからない場合（ModelNotFoundException は NotFoundHttpException に変換されて届く）
        $this->renderable(function (NotFoundHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['error' => '勤怠情報が見つかりませんでした。'], 404);
            }

            return null;
        });

        // Policy で拒否された場合（AuthorizationException は AccessDeniedHttpException に変換されて届く）
        $this->renderable(function (AccessDeniedHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*')) {
                return response()->json(['error' => 'この操作を実行する権限がありません。'], 403);
            }

            return null;
        });
    }

    /**
     * api/* は Accept ヘッダがなくても JSON で返す（401 の { "message": "Unauthenticated." }、422 のバリデーションエラーなど）
     *
     * 引数 $request の型は親クラスのメソッドが宣言していないため、型を付けると互換性エラーになる。型は PHPDoc で示す
     *
     * @param  Request  $request
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }
}
