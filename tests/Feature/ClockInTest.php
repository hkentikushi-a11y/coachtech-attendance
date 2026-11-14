<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース ID6：出勤機能
 */
class ClockInTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 09:05:00'));
        $this->user = User::factory()->create();
    }

    public function test_出勤ボタンが正しく機能する(): void
    {
        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('value="clock_in">出勤</button>', false);

        $this->post('/attendance', ['action' => 'clock_in'])->assertRedirect('/attendance');

        $this->get('/attendance')->assertSee('<p class="attendance__status--item">出勤中</p>', false);
    }

    public function test_出勤は一日一回のみできる(): void
    {
        AttendanceRecord::factory()->for($this->user)->create();

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('<p class="attendance__status--item">退勤済</p>', false)
            ->assertDontSee('value="clock_in"', false);

        // ボタンを使わずに送っても2回目の出勤は記録されない
        $this->post('/attendance', ['action' => 'clock_in']);
        $this->assertSame(1, $this->user->attendanceRecords()->count());
    }

    public function test_出勤時刻が勤怠一覧画面で確認できる(): void
    {
        $this->actingAs($this->user)->post('/attendance', ['action' => 'clock_in']);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->user->id,
            'date' => '2026-11-11',
            'clock_in' => '09:05:00',
        ]);
        $this->get('/attendance/list')->assertSeeInOrder(['11/11(水)', '09:05']);
    }
}
