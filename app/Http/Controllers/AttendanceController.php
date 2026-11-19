<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceStampRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AttendancePresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * 勤怠登録画面を表示する
     *
     * @param  Request  $request  リクエスト
     * @return View 表示する画面
     */
    public function index(Request $request): View
    {
        $now = now();

        return view('user.attendance-register', [
            'user' => $request->user(),
            'formattedDate' => $now->format('Y年n月j日').'('.AttendancePresenter::weekday($now).')',
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    /**
     * 出勤・休憩入・休憩戻・退勤を打刻する
     *
     * 現在のステータスで押せない打刻（退勤済みでの出勤など）は何もせずに戻る
     *
     * @param  AttendanceStampRequest  $request  打刻の種類（検証済み）
     * @return RedirectResponse リダイレクトレスポンス
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
     * 自分の月次勤怠一覧を表示する（?date=YYYY-MM で月を切り替える）
     *
     * @param  Request  $request  リクエスト
     * @return View 表示する画面
     */
    public function list(Request $request): View
    {
        $month = AttendancePresenter::monthFromQuery($request->query('date'));

        $records = $request->user()
            ->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get();

        return view('user.user-attendance-list', [
            'date' => $month,
            'previousMonth' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
            'formattedAttendanceRecords' => AttendancePresenter::monthlyRows($records, $month),
        ]);
    }

    /**
     * 自分の勤怠詳細を表示する。承認待ちの申請がある場合は申請内容を閲覧のみで表示する
     *
     * @param  Request  $request  リクエスト
     * @param  int  $id  勤怠ID
     * @return View 表示する画面
     */
    public function show(Request $request, int $id): View
    {
        $record = AttendanceRecord::with('breaks')->findOrFail($id);

        abort_unless($record->user_id === $request->user()->id, 403);

        $pendingApplication = $record->pendingApplication();
        $detail = AttendancePresenter::detail($record, $pendingApplication);

        return view('user.user-detail', [
            'user' => $request->user(),
            'data' => $pendingApplication ? $detail : AttendancePresenter::withOldInput($detail, $request->old()),
        ]);
    }

    /**
     * 出勤（勤務外のときのみ。1日1回）
     *
     * @param  User  $user  ユーザー
     * @param  ?AttendanceRecord  $record  今日の勤怠（なければ null）
     * @param  string  $time  打刻する時刻（HH:MM:SS）
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
     *
     * @param  ?AttendanceRecord  $record  今日の勤怠（なければ null）
     * @param  string  $time  打刻する時刻（HH:MM:SS）
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
     *
     * @param  ?AttendanceRecord  $record  今日の勤怠（なければ null）
     * @param  string  $time  打刻する時刻（HH:MM:SS）
     */
    private function breakOut(?AttendanceRecord $record, string $time): void
    {
        $record?->openBreak()?->update(['break_out' => $time]);
    }

    /**
     * 退勤（出勤中のときのみ。1日1回）
     *
     * @param  ?AttendanceRecord  $record  今日の勤怠（なければ null）
     * @param  string  $time  打刻する時刻（HH:MM:SS）
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
     *
     * @param  ?AttendanceRecord  $record  今日の勤怠（なければ null）
     * @return bool 出勤中なら true
     */
    private function isWorking(?AttendanceRecord $record): bool
    {
        return ! is_null($record) && is_null($record->clock_out) && is_null($record->openBreak());
    }
}
