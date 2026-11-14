<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| 一般ユーザーの会員登録・ログイン・ログアウト（/register, /login, /logout）は Fortify が定義する
|
*/

Route::redirect('/', '/attendance');

// 管理者の認証（認証処理は Fortify のコントローラーを使う）
Route::get('/admin/login', [AdminLoginController::class, 'create'])->middleware('guest');
Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])->middleware(['guest', 'throttle:login']);
Route::post('/admin/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth');

// 一般ユーザー
Route::middleware(['auth', 'general'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index']);
});

// 管理者
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/attendance/list', [AdminAttendanceController::class, 'index']);
});
