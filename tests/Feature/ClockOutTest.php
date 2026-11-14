<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース ID8：退勤機能
 */
class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_退勤ボタンが正しく機能する(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 18:00:00'));
        AttendanceRecord::factory()->working()->for($this->user)->create();

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('value="clock_out">退勤</button>', false);

        $this->post('/attendance', ['action' => 'clock_out'])->assertRedirect('/attendance');

        $this->get('/attendance')
            ->assertSee('<p class="attendance__status--item">退勤済</p>', false)
            ->assertSee('お疲れ様でした。');
    }

    public function test_退勤時刻が勤怠一覧画面で確認できる(): void
    {
        $this->actingAs($this->user);

        $this->travelTo(Carbon::parse('2026-11-11 09:00:00'));
        $this->post('/attendance', ['action' => 'clock_in']);
        $this->travelTo(Carbon::parse('2026-11-11 18:00:00'));
        $this->post('/attendance', ['action' => 'clock_out']);

        $this->assertDatabaseHas('attendance_records', ['user_id' => $this->user->id, 'clock_out' => '18:00:00']);
        $this->get('/attendance/list')->assertSeeInOrder(['11/11(水)', '09:00', '18:00']);
    }
}
