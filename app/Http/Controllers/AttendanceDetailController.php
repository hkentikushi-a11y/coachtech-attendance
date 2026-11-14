<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Application;
use App\Models\AttendanceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 提供 Blade が一般ユーザー・管理者で共通に使う /attendance/{id} を受け、ロールで処理を分ける
 */
class AttendanceDetailController extends Controller
{
    /**
     * 一覧の「詳細」リンク。ロールに応じた勤怠詳細画面へ移す
     */
    public function show(Request $request, int $id): RedirectResponse
    {
        return redirect($request->user()->admin_status ? "/admin/attendance/{$id}" : "/attendance/detail/{$id}");
    }

    /**
     * 勤怠の修正。一般ユーザーは修正申請を作成し、管理者は勤怠を直接修正する
     */
    public function update(AttendanceCorrectionRequest $request, int $id): RedirectResponse
    {
        $record = AttendanceRecord::findOrFail($id);
        $user = $request->user();

        if ($user->admin_status) {
            $this->correctDirectly($record, $request);

            return redirect("/admin/attendance/{$id}");
        }

        abort_unless($record->user_id === $user->id, 403);

        // 承認待ちの申請がある間は、新しく申請できない
        if (is_null($record->pendingApplication())) {
            $this->createApplication($record, $request);
        }

        return redirect("/attendance/detail/{$id}");
    }

    /**
     * 修正申請と申請の休憩を作成する
     */
    private function createApplication(AttendanceRecord $record, AttendanceCorrectionRequest $request): void
    {
        DB::transaction(function () use ($record, $request) {
            $application = $record->applications()->create([
                'user_id' => $record->user_id,
                'approval_status' => Application::STATUS_PENDING,
                'application_date' => today()->toDateString(),
                'new_date' => $record->date->toDateString(),
                'new_clock_in' => $request->input('new_clock_in'),
                'new_clock_out' => $request->input('new_clock_out'),
                'comment' => $request->input('comment'),
            ]);

            $application->proposalBreaks()->createMany($request->breaks());
        });
    }

    /**
     * 勤怠と休憩を入力内容で上書きする（管理者）
     */
    private function correctDirectly(AttendanceRecord $record, AttendanceCorrectionRequest $request): void
    {
        DB::transaction(function () use ($record, $request) {
            $record->update([
                'clock_in' => $request->input('new_clock_in'),
                'clock_out' => $request->input('new_clock_out'),
                'comment' => $request->input('comment'),
            ]);

            $record->breaks()->delete();
            $record->breaks()->createMany($request->breaks());
        });
    }
}
