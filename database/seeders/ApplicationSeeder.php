<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ApplicationSeeder extends Seeder
{
    /**
     * 管理者画面の動作確認用に、user2 の修正申請（承認済み1件・承認待ち1件）を作成する
     */
    public function run(): void
    {
        $records = User::where('email', 'user2@example.com')->firstOrFail()
            ->attendanceRecords()
            ->orderBy('date')
            ->get();

        $lastMonth = today()->subMonthNoOverflow();
        $approvedTarget = $records->first(fn (AttendanceRecord $record) => $record->date->isSameMonth($lastMonth));

        $approvedTarget->update(['clock_in' => '09:00', 'clock_out' => '18:30', 'comment' => '電車遅延のため']);
        $approvedTarget->breaks()->delete();
        $approvedTarget->breaks()->create(['break_in' => '12:00', 'break_out' => '13:00']);
        $this->createApplication($approvedTarget, Application::STATUS_APPROVED, $approvedTarget->date->copy()->addDay(), '18:30', '電車遅延のため');

        $lastWeekStart = today()->subWeek()->startOfWeek();
        $pendingTarget = $records->first(fn (AttendanceRecord $record) => $record->date->between($lastWeekStart, $lastWeekStart->copy()->endOfWeek()));

        $this->createApplication($pendingTarget, Application::STATUS_PENDING, today(), '18:00', '打刻漏れのため');
    }

    /**
     * 修正申請と申請の休憩（12:00-13:00）を作成する
     */
    private function createApplication(AttendanceRecord $record, string $status, Carbon $applicationDate, string $clockOut, string $comment): void
    {
        $application = Application::create([
            'user_id' => $record->user_id,
            'attendance_record_id' => $record->id,
            'approval_status' => $status,
            'application_date' => $applicationDate->toDateString(),
            'new_date' => $record->date->toDateString(),
            'new_clock_in' => '09:00',
            'new_clock_out' => $clockOut,
            'comment' => $comment,
        ]);

        $application->proposalBreaks()->create(['break_in' => '12:00', 'break_out' => '13:00']);
    }
}
