<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース ID17：★ 公開API 読み取り系
 */
class ApiReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_get一覧で勤怠一覧がjsonで取得できる(): void
    {
        $user = User::factory()->create();
        collect(range(1, 25))->each(fn (int $day) => AttendanceRecord::factory()->for($user)->create(['date' => sprintf('2026-10-%02d', $day)]));

        $this->getJson('/api/v1/attendance-records')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'user_id', 'user' => ['id', 'name'], 'date', 'clock_in', 'clock_out', 'total_time', 'total_break_time', 'comment']],
                'links',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonCount(20, 'data');
    }

    public function test_get一覧はuser_idとmonthで絞り込みper_pageで件数を変えられる(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-10-01']);
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-10-02']);
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-11-01']);
        AttendanceRecord::factory()->for($other)->create(['date' => '2026-10-01']);

        $this->getJson("/api/v1/attendance-records?user_id={$user->id}&month=2026-10&per_page=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.user_id', $user->id)
            ->assertJsonPath('data.0.date', '2026-10-02');

        $this->getJson('/api/v1/attendance-records?per_page=101')
            ->assertStatus(422)
            ->assertJsonPath('errors.per_page.0', '1ページあたりの件数は 100 以下で指定してください。');
    }

    public function test_get詳細で勤怠詳細がjsonで取得できる(): void
    {
        $user = User::factory()->create(['name' => 'API 太郎']);
        $record = AttendanceRecord::factory()->for($user)->create(['date' => '2026-11-10', 'clock_in' => '09:00:00', 'clock_out' => '18:00:00']);
        AttendanceBreak::factory()->for($record)->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
        Application::factory()->for($record)->for($user)->create(['comment' => '打刻漏れのため']);

        $this->getJson('/api/v1/attendance-records/' . $record->id)
            ->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.user.name', 'API 太郎')
            ->assertJsonPath('data.date', '2026-11-10')
            ->assertJsonPath('data.total_time', '08:00')
            ->assertJsonPath('data.total_break_time', '01:00')
            ->assertJsonPath('data.breaks.0.break_in', '12:00:00')
            ->assertJsonPath('data.breaks.0.break_out', '13:00:00')
            ->assertJsonPath('data.applications.0.comment', '打刻漏れのため');
    }

    public function test_存在しないidでは404とエラーjsonが返る(): void
    {
        $this->getJson('/api/v1/attendance-records/99999')
            ->assertNotFound()
            ->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }
}
