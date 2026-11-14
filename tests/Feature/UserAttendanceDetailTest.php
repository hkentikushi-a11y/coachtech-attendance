<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * テストケース ID10：勤怠詳細情報取得機能（一般ユーザー）
 */
class UserAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private TestResponse $response;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['name' => '勤怠 太郎']);
        $record = AttendanceRecord::factory()->for($user)->create(['date' => '2026-11-10', 'clock_in' => '08:50:00', 'clock_out' => '17:40:00']);
        AttendanceBreak::factory()->for($record)->create(['break_in' => '12:10:00', 'break_out' => '13:05:00']);

        $this->response = $this->actingAs($user)->get('/attendance/detail/' . $record->id);
    }

    public function test_勤怠詳細画面の名前がログインユーザーの氏名になっている(): void
    {
        $this->response->assertSee('name="name" value="勤怠 太郎"', false);
    }

    public function test_勤怠詳細画面の日付が選択した日付になっている(): void
    {
        $this->response->assertSeeInOrder(['value="2026年"', 'value="11月10日"'], false);
    }

    public function test_出勤退勤にて記されている時間がログインユーザーの打刻と一致している(): void
    {
        $this->response->assertSee('name="new_clock_in" value="08:50"', false);
        $this->response->assertSee('name="new_clock_out" value="17:40"', false);
    }

    public function test_休憩にて記されている時間がログインユーザーの打刻と一致している(): void
    {
        $this->response->assertSee('name="new_break_in[0]" value="12:10"', false);
        $this->response->assertSee('name="new_break_out[0]" value="13:05"', false);
    }
}
