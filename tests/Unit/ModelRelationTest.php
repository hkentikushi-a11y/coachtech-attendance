<?php

namespace Tests\Unit;

use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\ProposalBreak;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * モデルのリレーション（User / AttendanceRecord / AttendanceBreak / Application / ProposalBreak）
 */
class ModelRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_userは勤怠と修正申請を複数持つ(): void
    {
        $user = User::factory()->create();
        $records = AttendanceRecord::factory()->count(2)->for($user)->sequence(['date' => '2026-11-02'], ['date' => '2026-11-03'])->create();
        Application::factory()->for($records[0])->for($user)->create();

        $this->assertInstanceOf(HasMany::class, $user->attendanceRecords());
        $this->assertInstanceOf(HasMany::class, $user->applications());
        $this->assertEqualsCanonicalizing($records->pluck('id')->all(), $user->attendanceRecords()->pluck('id')->all());
        $this->assertSame(1, $user->applications()->count());
    }

    public function test_勤怠はユーザーに属し_休憩と修正申請を複数持つ(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create();
        AttendanceBreak::factory()->count(2)->for($record)->create();
        Application::factory()->for($record)->for($user)->create();

        $this->assertInstanceOf(BelongsTo::class, $record->user());
        $this->assertInstanceOf(HasMany::class, $record->breaks());
        $this->assertInstanceOf(HasMany::class, $record->applications());
        $this->assertTrue($record->user->is($user));
        $this->assertSame(2, $record->breaks()->count());
        $this->assertSame(1, $record->applications()->count());
    }

    public function test_休憩は勤怠に属する(): void
    {
        $break = AttendanceBreak::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $break->attendanceRecord());
        $this->assertSame('breaks', $break->getTable());
        $this->assertTrue($break->attendanceRecord->breaks->contains($break));
    }

    public function test_修正申請はユーザーと勤怠に属し_申請の休憩を複数持つ(): void
    {
        $application = Application::factory()->create();
        ProposalBreak::factory()->count(2)->for($application)->create();

        $this->assertInstanceOf(BelongsTo::class, $application->user());
        $this->assertInstanceOf(BelongsTo::class, $application->attendanceRecord());
        $this->assertInstanceOf(HasMany::class, $application->proposalBreaks());
        $this->assertTrue($application->user->is($application->attendanceRecord->user));
        $this->assertSame(2, $application->proposalBreaks()->count());
    }

    public function test_申請の休憩は修正申請に属する(): void
    {
        $proposalBreak = ProposalBreak::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $proposalBreak->application());
        $this->assertTrue($proposalBreak->application->proposalBreaks->contains($proposalBreak));
    }

    public function test_勤怠を削除すると休憩と修正申請と申請の休憩も削除される(): void
    {
        $record = AttendanceRecord::factory()->create();
        AttendanceBreak::factory()->for($record)->create();
        $application = Application::factory()->for($record)->for($record->user)->create();
        ProposalBreak::factory()->for($application)->create();

        $record->delete();

        $this->assertDatabaseCount('breaks', 0);
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('proposal_breaks', 0);
    }
}
