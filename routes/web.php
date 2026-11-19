<?php

use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceDetailController;
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

// 一般ユーザー（メール認証が済んでいない場合は、メール認証誘導画面へ移される）
Route::middleware(['auth', 'verified', 'general'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::get('/attendance/list', [AttendanceController::class, 'list']);
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])->whereNumber('id');
    Route::get('/application/{id}', [ApplicationController::class, 'show'])->whereNumber('id');
});

// 一般ユーザー・管理者で共通（提供 Blade が同じ URL を使うため、コントローラー内でロールにより処理を分ける）
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance/{id}', [AttendanceDetailController::class, 'show'])->whereNumber('id');
    Route::post('/attendance/{id}', [AttendanceDetailController::class, 'update'])->whereNumber('id');
    Route::get('/stamp_correction_request/list', [ApplicationController::class, 'index']);
});

// 管理者
Route::middleware(['auth', 'admin'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/attendance/list', [AdminAttendanceController::class, 'index']);
        Route::get('/attendance/{id}', [AdminAttendanceController::class, 'show'])->whereNumber('id');
        Route::get('/staff/list', [StaffController::class, 'index']);
        Route::get('/attendance/staff/{id}', [AdminAttendanceController::class, 'staff'])->whereNumber('id');
    });

    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminApplicationController::class, 'show'])->whereNumber('attendance_correct_request_id');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminApplicationController::class, 'approve'])->whereNumber('attendance_correct_request_id');
    Route::post('/export', [AdminAttendanceController::class, 'export']);
});
