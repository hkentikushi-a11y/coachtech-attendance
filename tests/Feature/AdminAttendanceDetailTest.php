<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * テストケース ID13：勤怠詳細情報取得・修正機能（管理者）
 */
class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => '詳細 太郎']);
        $this->record = AttendanceRecord::factory()->for($user)->create(['date' => '2026-11-10', 'clock_in' => '08:40:00', 'clock_out' => '17:50:00', 'comment' => '通常勤務']);
        AttendanceBreak::factory()->for($this->record)->create(['break_in' => '12:15:00', 'break_out' => '13:10:00']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)
            ->from('/admin/attendance/' . $this->record->id)
            ->post('/attendance/' . $this->record->id, array_merge([
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['12:00', ''],
                'new_break_out' => ['13:00', ''],
                'comment' => '管理者による修正',
            ], $overrides));
    }

    public function test_勤怠詳細画面に表示されるデータが選択したものになっている(): void
    {
        $this->actingAs($this->admin)->get('/admin/attendance/' . $this->record->id)
            ->assertOk()
            ->assertSee('name="name" value="詳細 太郎"', false)
            ->assertSeeInOrder(['value="2026年"', 'value="11月10日"'], false)
            ->assertSeeInOrder(['name="new_clock_in"', 'value="08:40"', 'name="new_clock_out"', 'value="17:50"'], false)
            ->assertSeeInOrder(['name="new_break_in[0]"', 'value="12:15"', 'name="new_break_out[0]"', 'value="13:10"'], false)
            ->assertSee('通常勤務');
    }

    public function test_出勤時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit(['new_clock_in' => '19:00'])
            ->assertSessionHasErrors(['new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です']);
    }

    public function test_休憩開始時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit(['new_break_in' => ['19:00', ''], 'new_break_out' => ['19:30', '']])
            ->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    public function test_休憩終了時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit(['new_break_out' => ['18:30', '']])
            ->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    public function test_備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        $this->submit(['comment' => ''])
            ->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    public function test_修正すると申請を挟まずに勤怠が更新される(): void
    {
        $this->submit(['new_clock_out' => '18:45'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/attendance/' . $this->record->id);

        $this->assertDatabaseHas('attendance_records', ['id' => $this->record->id, 'clock_in' => '09:00:00', 'clock_out' => '18:45:00', 'comment' => '管理者による修正']);
        $this->assertDatabaseHas('breaks', ['attendance_record_id' => $this->record->id, 'break_in' => '12:00:00', 'break_out' => '13:00:00']);
        $this->assertDatabaseCount('applications', 0);
    }
}
