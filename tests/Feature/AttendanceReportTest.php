<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース ID20：★ マイ勤怠レポート機能
 */
class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-19 10:00:00'));
    }

    /**
     * 休憩 12:00-13:00 付きの勤怠を作る
     */
    private function work(User $user, string $date, string $clockIn, ?string $clockOut): void
    {
        $record = AttendanceRecord::factory()->for($user)->create(['date' => $date, 'clock_in' => $clockIn, 'clock_out' => $clockOut]);
        AttendanceBreak::factory()->for($record)->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
    }

    public function test_ゲストはレポートページにアクセスできない(): void
    {
        $this->get('/attendance/report')->assertRedirect('/login');
    }

    public function test_認証ユーザーの統計情報が正しく計算される(): void
    {
        $user = User::factory()->create();
        $this->work($user, '2026-06-10', '09:00:00', '18:00:00'); // 8h（5ヶ月前の月。集計対象）
        $this->work($user, '2026-05-29', '09:00:00', '18:00:00'); // 6ヶ月前の月。集計対象外
        $this->work($user, '2026-10-05', '09:00:00', '20:30:00'); // 10h30m（残業 2h30m・長時間だが先月）
        $this->work($user, '2026-11-02', '09:15:00', '18:00:00'); // 7h45m（遅刻）
        $this->work($user, '2026-11-03', '09:00:00', '17:30:00'); // 7h30m（早退）
        $this->work($user, '2026-11-04', '08:00:00', '20:00:00'); // 11h（残業 3h・長時間）
        $this->work($user, '2026-11-05', '09:00:00', '19:00:00'); // 9h（残業 1h。ちょうど10h未満なので長時間ではない）
        $this->work($user, '2026-11-19', '09:00:00', null);       // 出勤中（未退勤）は集計しない
        $this->work(User::factory()->create(), '2026-11-02', '09:00:00', '23:00:00'); // 他人の勤怠は集計しない

        $total = 480 + 630 + 465 + 450 + 660 + 540;

        $this->actingAs($user)->get('/attendance/report')
            ->assertOk()
            ->assertViewHas('summary', [
                'total_work_minutes' => $total,
                'total_overtime_minutes' => 150 + 180 + 60,
                'avg_work_minutes' => intdiv($total, 6),
            ])
            ->assertViewHas('monthlyTrend', [
                ['month' => '2026/06', 'work_minutes' => 480, 'overtime_minutes' => 0],
                ['month' => '2026/07', 'work_minutes' => 0, 'overtime_minutes' => 0],
                ['month' => '2026/08', 'work_minutes' => 0, 'overtime_minutes' => 0],
                ['month' => '2026/09', 'work_minutes' => 0, 'overtime_minutes' => 0],
                ['month' => '2026/10', 'work_minutes' => 630, 'overtime_minutes' => 150],
                ['month' => '2026/11', 'work_minutes' => 465 + 450 + 660 + 540, 'overtime_minutes' => 180 + 60],
            ])
            ->assertViewHas('anomalies', ['late_count' => 1, 'early_leave_count' => 1, 'long_work_count' => 1])
            ->assertSee('53h 45m');
    }

    public function test_seederの意図的データで要件シートの予測値どおりになる(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'user1@example.com')->firstOrFail();

        $this->actingAs($user)->get('/attendance/report')
            ->assertViewHas('summary', [
                'total_work_minutes' => 744 * 60,
                'total_overtime_minutes' => 10 * 60,
                'avg_work_minutes' => 8 * 60 + 5,
            ])
            ->assertViewHas('anomalies', ['late_count' => 2, 'early_leave_count' => 1, 'long_work_count' => 1])
            ->assertSee('744h 0m')
            ->assertSee('8h 5m');
    }

    public function test_勤怠記録がないユーザーで安全に処理される(): void
    {
        $this->actingAs(User::factory()->create())->get('/attendance/report')
            ->assertOk()
            ->assertViewHas('summary', ['total_work_minutes' => 0, 'total_overtime_minutes' => 0, 'avg_work_minutes' => 0])
            ->assertViewHas('monthlyTrend', fn (array $trend) => count($trend) === 6
                && collect($trend)->every(fn (array $row) => $row['work_minutes'] === 0 && $row['overtime_minutes'] === 0))
            ->assertViewHas('anomalies', ['late_count' => 0, 'early_leave_count' => 0, 'long_work_count' => 0]);
    }
}
