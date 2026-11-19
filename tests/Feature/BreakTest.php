<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース ID7：休憩機能
 */
class BreakTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
        $this->user = User::factory()->create();
        AttendanceRecord::factory()->working()->for($this->user)->create(['clock_in' => '09:00:00']);
        $this->actingAs($this->user);
    }

    /**
     * 打刻する（勤怠登録画面へ戻ることも確認する）
     */
    private function stamp(string $action): void
    {
        $this->post('/attendance', ['action' => $action])->assertRedirect('/attendance');
    }

    public function test_休憩ボタンが正しく機能する(): void
    {
        $this->get('/attendance')->assertSee('value="break_in">休憩入</button>', false);

        $this->stamp('break_in');

        $this->get('/attendance')->assertSee('<p class="attendance__status--item">休憩中</p>', false);
    }

    public function test_休憩は一日に何回でもできる(): void
    {
        $this->stamp('break_in');
        $this->stamp('break_out');

        $this->get('/attendance')->assertSee('value="break_in">休憩入</button>', false);
    }

    public function test_休憩戻ボタンが正しく機能する(): void
    {
        $this->stamp('break_in');
        $this->get('/attendance')->assertSee('value="break_out">休憩戻</button>', false);

        $this->stamp('break_out');

        $this->get('/attendance')->assertSee('<p class="attendance__status--item">出勤中</p>', false);
    }

    public function test_休憩戻は一日に何回でもできる(): void
    {
        $this->stamp('break_in');
        $this->stamp('break_out');
        $this->stamp('break_in');

        $this->get('/attendance')->assertSee('value="break_out">休憩戻</button>', false);
    }

    public function test_休憩時刻が勤怠一覧画面で確認できる(): void
    {
        $this->stamp('break_in');
        $this->travelTo(Carbon::parse('2026-11-11 12:30:00'));
        $this->stamp('break_out');

        $this->assertDatabaseHas('breaks', ['break_in' => '12:00:00', 'break_out' => '12:30:00']);
        // 勤怠一覧の「休憩」欄には休憩時間の合計が表示される
        $this->get('/attendance/list')->assertSeeInOrder(['11/11(水)', '09:00', '0:30']);
    }
}
