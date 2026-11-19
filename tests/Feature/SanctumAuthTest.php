<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * テストケース ID19：★ Sanctum 認証
 */
class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->record = AttendanceRecord::factory()->for($this->owner)->create();
    }

    public function test_未認証時に書き込み系apiで401が返る(): void
    {
        $this->postJson('/api/v1/attendance-records', ['date' => '2026-12-01', 'clock_in' => '09:00:00'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
        $this->putJson('/api/v1/attendance-records/' . $this->record->id, ['clock_out' => '19:00:00'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
        $this->deleteJson('/api/v1/attendance-records/' . $this->record->id)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->assertDatabaseHas('attendance_records', ['id' => $this->record->id]);
    }

    public function test_acceptヘッダがなくても401はjsonで返る(): void
    {
        $this->post('/api/v1/attendance-records', ['date' => '2026-12-01', 'clock_in' => '09:00:00'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_認証済みユーザーは自分の勤怠を更新削除できる(): void
    {
        Sanctum::actingAs($this->owner, ['*']);

        $this->putJson('/api/v1/attendance-records/' . $this->record->id, ['comment' => '本人が更新'])->assertOk();
        $this->assertDatabaseHas('attendance_records', ['id' => $this->record->id, 'comment' => '本人が更新']);

        $this->deleteJson('/api/v1/attendance-records/' . $this->record->id)->assertNoContent();
        $this->assertDatabaseMissing('attendance_records', ['id' => $this->record->id]);
    }

    public function test_他ユーザーの勤怠を更新削除しようとすると403が返る(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->putJson('/api/v1/attendance-records/' . $this->record->id, ['comment' => '他人が更新'])
            ->assertForbidden()
            ->assertExactJson(['error' => 'この操作を実行する権限がありません。']);
        $this->deleteJson('/api/v1/attendance-records/' . $this->record->id)
            ->assertForbidden()
            ->assertExactJson(['error' => 'この操作を実行する権限がありません。']);

        $this->assertDatabaseHas('attendance_records', ['id' => $this->record->id, 'comment' => null]);
    }

    public function test_管理者は他ユーザーの勤怠を更新削除できる(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);

        $this->putJson('/api/v1/attendance-records/' . $this->record->id, ['comment' => '管理者が更新'])->assertOk();
        $this->assertDatabaseHas('attendance_records', ['id' => $this->record->id, 'comment' => '管理者が更新']);

        $this->deleteJson('/api/v1/attendance-records/' . $this->record->id)->assertNoContent();
        $this->assertDatabaseMissing('attendance_records', ['id' => $this->record->id]);
    }
}
