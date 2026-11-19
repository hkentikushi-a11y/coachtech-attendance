<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * 管理者ログイン画面（認証処理は Fortify の AuthenticatedSessionController が行う）
 */
class LoginController extends Controller
{
    /**
     * 管理者ログイン画面を表示する
     *
     * @return View 表示する画面
     */
    public function create(): View
    {
        return view('admin.admin-login');
    }
}
