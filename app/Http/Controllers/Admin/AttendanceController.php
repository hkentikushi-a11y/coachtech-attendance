<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    /**
     * その日の全ユーザーの勤怠一覧を表示する（?date=YYYY-MM-DD で日を切り替える。不正な値は今日として扱う）
     */
    public function index(Request $request): View
    {
        $date = $request->filled('date')
            ? rescue(fn () => Carbon::createFromFormat('!Y-m-d', $request->query('date')), today(), false)
            : today();

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->copy()->subDay()->toDateString(),
            'nextDay' => $date->copy()->addDay()->toDateString(),
            'users' => User::where('admin_status', false)->orderBy('id')->get(),
            'attendanceRecords' => AttendanceRecord::with('breaks')->whereDate('date', $date)->get(),
        ]);
    }
}
