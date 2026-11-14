<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AttendancePresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * その日の全ユーザーの勤怠一覧を表示する（?date=YYYY-MM-DD で日を切り替える）
     */
    public function index(Request $request): View
    {
        $date = AttendancePresenter::dayFromQuery($request->query('date'));

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->copy()->subDay()->toDateString(),
            'nextDay' => $date->copy()->addDay()->toDateString(),
            'users' => User::where('admin_status', false)->orderBy('id')->get(),
            'attendanceRecords' => AttendanceRecord::with('breaks')->whereDate('date', $date)->get(),
        ]);
    }

    /**
     * 選択したユーザーの月次勤怠一覧を表示する（?date=YYYY-MM で月を切り替える）
     */
    public function staff(Request $request, int $id): View
    {
        $user = User::where('admin_status', false)->findOrFail($id);
        $month = AttendancePresenter::monthFromQuery($request->query('date'));

        $records = $user->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get();

        return view('admin.staff-attendance-list', [
            'user' => $user,
            'date' => $month,
            'previousMonth' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
            'formattedAttendanceRecords' => AttendancePresenter::monthlyRows($records, $month),
        ]);
    }

    /**
     * 勤怠詳細を表示する（修正は POST /attendance/{id} で直接反映する）
     */
    public function show(Request $request, int $id): View
    {
        $record = AttendanceRecord::with(['user', 'breaks'])->findOrFail($id);

        return view('admin.admin-detail', [
            'user' => $record->user,
            'attendanceRecord' => AttendancePresenter::withOldInput(AttendancePresenter::detail($record), $request->old()),
        ]);
    }
}
