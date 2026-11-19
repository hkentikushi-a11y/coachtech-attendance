<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * テストケース ID18：★ 公開API 書き込み系
 */
class ApiWriteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_postで勤怠が作成される(): void
    {
        $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-12-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'API から登録',
        ])
            ->assertCreated()
            ->assertJsonPath('data.date', '2026-12-01')
            ->assertJsonPath('data.user_id', $this->user->id);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->user->id,
            'date' => '2026-12-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'API から登録',
        ]);
    }

    public function test_バリデーションエラー時に422と日本語エラーメッセージが返る(): void
    {
        $this->postJson('/api/v1/attendance-records', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', '勤怠日は必須です。')
            ->assertJsonPath('errors.clock_in.0', '出勤時刻は必須です。');

        $this->postJson('/api/v1/attendance-records', ['date' => '2026/12/01', 'clock_in' => '9:00', 'clock_out' => '18:00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', '勤怠日は YYYY-MM-DD 形式で指定してください。')
            ->assertJsonPath('errors.clock_in.0', '出勤時刻は HH:MM:SS 形式で指定してください。')
            ->assertJsonPath('errors.clock_out.0', '退勤時刻は HH:MM:SS 形式で指定してください。');

        $this->postJson('/api/v1/attendance-records', ['date' => '2026-12-01', 'clock_in' => '18:00:00', 'clock_out' => '09:00:00', 'comment' => str_repeat('あ', 256)])
            ->assertStatus(422)
            ->assertJsonPath('errors.clock_out.0', '退勤時刻は出勤時刻より後の時刻を指定してください。')
            ->assertJsonPath('errors.comment.0', '備考は 255 文字以内で入力してください。');

        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-12-02']);
        $this->postJson('/api/v1/attendance-records', ['date' => '2026-12-02', 'clock_in' => '09:00:00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', 'この日付の勤怠は既に登録されています。');
    }

    public function test_putで勤怠が更新され_存在しないidでは404が返る(): void
    {
        $record = AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-12-01', 'clock_in' => '09:00:00', 'clock_out' => '18:00:00']);

        $this->putJson('/api/v1/attendance-records/'.$record->id, ['clock_out' => '19:30:00', 'comment' => '残業'])
            ->assertOk()
            ->assertJsonPath('data.clock_out', '19:30:00');

        // 送信していない項目（date・clock_in）は登録済みの値のまま
        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'date' => '2026-12-01', 'clock_in' => '09:00:00', 'clock_out' => '19:30:00', 'comment' => '残業']);

        $this->putJson('/api/v1/attendance-records/99999', ['clock_out' => '19:30:00'])
            ->assertNotFound()
            ->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }

    public function test_putのclock_outは登録済みの出勤時刻と比較される(): void
    {
        $record = AttendanceRecord::factory()->for($this->user)->create(['clock_in' => '09:00:00']);

        $this->putJson('/api/v1/attendance-records/'.$record->id, ['clock_out' => '08:00:00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.clock_out.0', '退勤時刻は出勤時刻より後の時刻を指定してください。');
    }

    public function test_deleteで勤怠が削除され_存在しないidでは404が返る(): void
    {
        $record = AttendanceRecord::factory()->for($this->user)->create();
        AttendanceBreak::factory()->for($record)->create();

        $this->deleteJson('/api/v1/attendance-records/'.$record->id)->assertNoContent();

        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
        $this->assertDatabaseMissing('breaks', ['attendance_record_id' => $record->id]);

        $this->deleteJson('/api/v1/attendance-records/99999')
            ->assertNotFound()
            ->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }
}
