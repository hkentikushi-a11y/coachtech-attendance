<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース ID12：勤怠一覧情報取得機能（管理者）
 */
class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $first;

    private User $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 20:00:00'));
        $this->admin = User::factory()->admin()->create(['name' => '管理 者']);
        $this->first = User::factory()->create(['name' => '一番 太郎']);
        $this->second = User::factory()->create(['name' => '二番 花子']);
    }

    public function test_その日になされた全ユーザーの勤怠情報が正確に確認できる(): void
    {
        $record = AttendanceRecord::factory()->for($this->first)->create(['clock_in' => '09:00:00', 'clock_out' => '18:00:00']);
        AttendanceBreak::factory()->for($record)->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
        AttendanceRecord::factory()->for($this->second)->create(['clock_in' => '10:00:00', 'clock_out' => '19:30:00']);
        AttendanceRecord::factory()->for($this->second)->create(['date' => '2026-11-10', 'clock_in' => '07:07:00']);
        AttendanceRecord::factory()->for($this->admin)->create(['clock_in' => '08:10:00', 'clock_out' => '17:10:00']);

        $response = $this->actingAs($this->admin)->get('/admin/attendance/list');

        // 名前・出勤・退勤・休憩・合計（「全ユーザー」なので勤怠のある管理者も含む）
        $response->assertSeeInOrder(['管理 者', '08:10', '17:10', '0:00', '9:00', '一番 太郎', '09:00', '18:00', '1:00', '8:00', '二番 花子', '10:00', '19:30', '0:00', '9:30']);
        $response->assertDontSee('07:07');
    }

    public function test_遷移した際に現在の日付が表示される(): void
    {
        $this->actingAs($this->admin)->get('/admin/attendance/list')
            ->assertSee('2026年11月11日の勤怠')
            ->assertSee('<p class="current-day">2026/11/11</p>', false);
    }

    public function test_前日を押下した時に前の日の勤怠情報が表示される(): void
    {
        AttendanceRecord::factory()->for($this->first)->create(['date' => '2026-11-10', 'clock_in' => '08:15:00', 'clock_out' => '17:15:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/list')
            ->assertSee('href="?date=2026-11-10"', false);

        $this->get('/admin/attendance/list?date=2026-11-10')
            ->assertSee('<p class="current-day">2026/11/10</p>', false)
            ->assertSeeInOrder(['一番 太郎', '08:15', '17:15']);
    }

    public function test_翌日を押下した時に次の日の勤怠情報が表示される(): void
    {
        AttendanceRecord::factory()->for($this->second)->create(['date' => '2026-11-12', 'clock_in' => '11:00:00', 'clock_out' => '20:00:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/list')
            ->assertSee('href="?date=2026-11-12"', false);

        $this->get('/admin/attendance/list?date=2026-11-12')
            ->assertSee('<p class="current-day">2026/11/12</p>', false)
            ->assertSeeInOrder(['二番 花子', '11:00', '20:00']);
    }
}
