<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ★ CSV 出力機能（FN045）
 */
class AttendanceExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->staff = User::factory()->create(['name' => '出力 太郎']);
    }

    public function test_csv出力を押下すると選択したユーザーと月の勤怠一覧がcsvでダウンロードされる(): void
    {
        $record = AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-11-02', 'clock_in' => '09:00:00', 'clock_out' => '18:30:00', 'comment' => '電車遅延のため']);
        AttendanceBreak::factory()->for($record)->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
        AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-11-04', 'clock_in' => '08:45:00', 'clock_out' => '17:45:00']);
        AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-10-30']);
        AttendanceRecord::factory()->create(['date' => '2026-11-02', 'clock_in' => '07:07:00']);

        $response = $this->actingAs($this->admin)->post('/export', ['user_id' => $this->staff->id, 'year_month' => '2026-11']);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(rawurlencode('出力 太郎_2026年11月_勤怠一覧.csv'), $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}", $csv);
        $this->assertSame(
            "\u{FEFF}日付,出勤,退勤,休憩,合計,備考\r\n"
            ."2026/11/02,09:00,18:30,1:00,8:30,電車遅延のため\r\n"
            ."2026/11/04,08:45,17:45,0:00,9:00,\r\n",
            $csv
        );
    }

    public function test_勤怠のない月はヘッダだけを出力する(): void
    {
        $csv = $this->actingAs($this->admin)
            ->post('/export', ['user_id' => $this->staff->id, 'year_month' => '2026-12'])
            ->streamedContent();

        $this->assertSame("\u{FEFF}日付,出勤,退勤,休憩,合計,備考\r\n", $csv);
    }

    public function test_不正な指定ではcsvを出力しない(): void
    {
        $this->actingAs($this->admin)->post('/export', ['user_id' => $this->staff->id, 'year_month' => '2026/11'])
            ->assertSessionHasErrors(['year_month' => '年月は YYYY-MM 形式で指定してください']);
        $this->post('/export', ['user_id' => $this->admin->id, 'year_month' => '2026-11'])
            ->assertSessionHasErrors(['user_id' => 'ユーザーの指定が不正です']);
    }

    public function test_一般ユーザーはcsv出力できない(): void
    {
        $this->actingAs($this->staff)
            ->post('/export', ['user_id' => $this->staff->id, 'year_month' => '2026-11'])
            ->assertRedirect('/attendance');
    }
}
