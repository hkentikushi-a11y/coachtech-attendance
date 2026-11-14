<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;

class StaffController extends Controller
{
    /**
     * 全一般ユーザーの氏名とメールアドレスを表示する
     */
    public function index(): View
    {
        return view('admin.staff-list', [
            'users' => User::where('admin_status', false)->orderBy('id')->get(),
        ]);
    }
}
