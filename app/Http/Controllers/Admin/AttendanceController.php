<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceExportRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AttendancePresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    private const CSV_HEADER = ['日付', '出勤', '退勤', '休憩', '合計', '備考'];

    /**
     * その日の全ユーザーの勤怠一覧を表示する（?date=YYYY-MM-DD で日を切り替える）
     *
     * @param  Request  $request  リクエスト
     * @return View 表示する画面
     */
    public function index(Request $request): View
    {
        $date = AttendancePresenter::dayFromQuery($request->query('date'));

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->copy()->subDay()->toDateString(),
            'nextDay' => $date->copy()->addDay()->toDateString(),
            // FN034「その日になされた全ユーザーの勤怠情報」のため管理者も含める（勤怠がないユーザーの行は Blade 側で表示しない）
            'users' => User::orderBy('id')->get(),
            'attendanceRecords' => AttendanceRecord::with('breaks')->whereDate('date', $date)->get(),
        ]);
    }

    /**
     * 選択したユーザーの月次勤怠一覧を表示する（?date=YYYY-MM で月を切り替える）
     *
     * @param  Request  $request  リクエスト
     * @param  int  $id  ユーザーID
     * @return View 表示する画面
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
     *
     * @param  Request  $request  リクエスト
     * @param  int  $id  勤怠ID
     * @return View 表示する画面
     */
    public function show(Request $request, int $id): View
    {
        $record = AttendanceRecord::with(['user', 'breaks'])->findOrFail($id);

        return view('admin.admin-detail', [
            'user' => $record->user,
            'attendanceRecord' => AttendancePresenter::withOldInput(AttendancePresenter::detail($record), $request->old()),
        ]);
    }

    /**
     * 選択したユーザー・月の勤怠一覧を CSV でダウンロードさせる
     *
     * Excel で開いても文字化けしないよう、BOM 付きの UTF-8・改行 CRLF で出力する
     *
     * @param  AttendanceExportRequest  $request  出力するユーザーと年月（検証済み）
     * @return StreamedResponse CSV のダウンロードレスポンス
     */
    public function export(AttendanceExportRequest $request): StreamedResponse
    {
        $user = User::findOrFail($request->integer('user_id'));
        $month = Carbon::createFromFormat('!Y-m', $request->input('year_month'));

        $rows = $user->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(fn (AttendanceRecord $record) => [
                $record->date->format('Y/m/d'),
                AttendancePresenter::time($record->clock_in),
                AttendancePresenter::time($record->clock_out),
                $record->total_break_time,
                $record->total_time ?? '',
                $record->comment ?? '',
            ]);

        $fileName = "{$user->name}_{$month->format('Y年m月')}_勤怠一覧.csv";

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\u{FEFF}");
            collect([self::CSV_HEADER])
                ->concat($rows)
                ->each(fn (array $row) => fputcsv($output, $row, eol: "\r\n"));
            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
