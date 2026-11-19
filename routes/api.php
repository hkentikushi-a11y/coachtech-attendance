<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 公開 API v1（/api/v1/attendance-records）
| 読み取り系（GET）は認証不要、書き込み系（POST / PUT / PATCH / DELETE）は Sanctum のトークン認証が必要
|
*/

Route::prefix('v1')->group(function () {
    Route::apiResource('attendance-records', AttendanceRecordController::class)->only(['index', 'show'])
        ->parameters(['attendance-records' => 'attendanceRecord']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('attendance-records', AttendanceRecordController::class)->only(['store', 'update', 'destroy'])
            ->parameters(['attendance-records' => 'attendanceRecord']);
    });
});
