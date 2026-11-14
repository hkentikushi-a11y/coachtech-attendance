<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceStampRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /**
     * 勤怠登録画面を表示する
     */
    public function index(Request $request): View
    {
        $now = now();

        return view('user.attendance-register', [
            'user' => $request->user(),
            'formattedDate' => $now->format('Y年n月j日') . '(' . self::WEEKDAYS[$now->dayOfWeek] . ')',
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    /**
     * 出勤・休憩入・休憩戻・退勤を打刻する
     *
     * 現在のステータスで押せない打刻（退勤済みでの出勤など）は何もせずに戻る
     */
    public function store(AttendanceStampRequest $request): RedirectResponse
    {
        $user = $request->user();
        $record = $user->todayAttendanceRecord();
        $time = now()->format('H:i:s');

        match ($request->input('action')) {
            'clock_in' => $this->clockIn($user, $record, $time),
            'break_in' => $this->breakIn($record, $time),
            'break_out' => $this->breakOut($record, $time),
            'clock_out' => $this->clockOut($record, $time),
        };

        return redirect('/attendance');
    }

    /**
     * 出勤（勤務外のときのみ。1日1回）
     */
    private function clockIn(User $user, ?AttendanceRecord $record, string $time): void
    {
        if (! is_null($record)) {
            return;
        }

        $user->attendanceRecords()->create([
            'date' => today()->toDateString(),
            'clock_in' => $time,
        ]);
    }

    /**
     * 休憩入（出勤中のときのみ。1日に何回でも）
     */
    private function breakIn(?AttendanceRecord $record, string $time): void
    {
        if (! $this->isWorking($record)) {
            return;
        }

        $record->breaks()->create(['break_in' => $time]);
    }

    /**
     * 休憩戻（休憩中のときのみ）
     */
    private function breakOut(?AttendanceRecord $record, string $time): void
    {
        $record?->openBreak()?->update(['break_out' => $time]);
    }

    /**
     * 退勤（出勤中のときのみ。1日1回）
     */
    private function clockOut(?AttendanceRecord $record, string $time): void
    {
        if (! $this->isWorking($record)) {
            return;
        }

        $record->update(['clock_out' => $time]);
    }

    /**
     * 出勤中（出勤済み・未退勤・休憩中でない）かどうか
     */
    private function isWorking(?AttendanceRecord $record): bool
    {
        return ! is_null($record) && is_null($record->clock_out) && is_null($record->openBreak());
    }
}
