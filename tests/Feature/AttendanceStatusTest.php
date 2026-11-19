<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース ID5：ステータス確認機能
 */
class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 15:00:00'));
        $this->user = User::factory()->create();
    }

    /**
     * 勤怠登録画面に指定したステータスが表示されていることを確認する
     */
    private function assertStatus(string $status): void
    {
        $this->actingAs($this->user)
            ->get('/attendance')
            ->assertSee('<p class="attendance__status--item">' . $status . '</p>', false);
    }

    public function test_勤務外の場合_勤怠ステータスが正しく表示される(): void
    {
        $this->assertStatus('勤務外');
    }

    public function test_出勤中の場合_勤怠ステータスが正しく表示される(): void
    {
        AttendanceRecord::factory()->working()->for($this->user)->create();

        $this->assertStatus('出勤中');
    }

    public function test_休憩中の場合_勤怠ステータスが正しく表示される(): void
    {
        $record = AttendanceRecord::factory()->working()->for($this->user)->create();
        AttendanceBreak::factory()->for($record)->create(['break_in' => '12:00:00', 'break_out' => null]);

        $this->assertStatus('休憩中');
    }

    public function test_退勤済の場合_勤怠ステータスが正しく表示される(): void
    {
        AttendanceRecord::factory()->for($this->user)->create();

        $this->assertStatus('退勤済');
    }
}
