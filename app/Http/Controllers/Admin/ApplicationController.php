<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ProposalBreak;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    /**
     * 修正申請の承認画面を表示する
     */
    public function show(int $attendance_correct_request_id): View
    {
        $application = Application::with(['user', 'proposalBreaks'])->findOrFail($attendance_correct_request_id);

        return view('admin.admin-application-detail', [
            'user' => $application->user,
            'application' => $application,
        ]);
    }

    /**
     * 修正申請を承認し、申請内容で勤怠と休憩を更新する（承認待ちの申請のみ）
     */
    public function approve(int $attendance_correct_request_id): RedirectResponse
    {
        $application = Application::with(['attendanceRecord', 'proposalBreaks'])->findOrFail($attendance_correct_request_id);

        if ($application->approval_status === Application::STATUS_PENDING) {
            DB::transaction(function () use ($application) {
                $record = $application->attendanceRecord;

                $record->update([
                    'clock_in' => $application->new_clock_in,
                    'clock_out' => $application->new_clock_out,
                    'comment' => $application->comment,
                ]);

                $record->breaks()->delete();
                $record->breaks()->createMany(
                    $application->proposalBreaks
                        ->map(fn (ProposalBreak $break) => ['break_in' => $break->break_in, 'break_out' => $break->break_out])
                        ->all()
                );

                $application->update(['approval_status' => Application::STATUS_APPROVED]);
            });
        }

        return redirect("/stamp_correction_request/approve/{$attendance_correct_request_id}");
    }
}
