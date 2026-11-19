<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\ProposalBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * テストケース ID15：勤怠情報修正機能（管理者）
 */
class AdminApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * 指定したユーザーの勤怠に対する申請を作成する
     */
    private function makeApplication(User $user, string $date, string $comment, bool $approved = false): Application
    {
        $record = AttendanceRecord::factory()->for($user)->create(['date' => $date]);
        $factory = Application::factory()->for($record)->for($user);

        return ($approved ? $factory->approved() : $factory)->create(['comment' => $comment]);
    }

    /**
     * 申請一覧の [承認待ちタブ, 承認済みタブ] の HTML
     *
     * @return array{0: string, 1: string}
     */
    private function tabs(TestResponse $response): array
    {
        [$pending, $approved] = explode('id="content2"', $response->getContent());

        return [$pending, $approved];
    }

    public function test_承認待ちの修正申請が全て表示されている(): void
    {
        $this->makeApplication(User::factory()->create(['name' => '一番 太郎']), '2026-11-02', '一番の申請');
        $this->makeApplication(User::factory()->create(['name' => '二番 花子']), '2026-11-03', '二番の申請');
        $this->makeApplication(User::factory()->create(['name' => '三番 次郎']), '2026-11-04', '承認済みの申請', true);

        [$pending] = $this->tabs($this->actingAs($this->admin)->get('/stamp_correction_request/list'));

        $this->assertStringContainsString('一番の申請', $pending);
        $this->assertStringContainsString('二番の申請', $pending);
        $this->assertStringNotContainsString('承認済みの申請', $pending);
    }

    public function test_承認済みの修正申請が全て表示されている(): void
    {
        $this->makeApplication(User::factory()->create(), '2026-11-02', '承認済み1', true);
        $this->makeApplication(User::factory()->create(), '2026-11-03', '承認済み2', true);
        $this->makeApplication(User::factory()->create(), '2026-11-04', '承認待ちの申請');

        [, $approved] = $this->tabs($this->actingAs($this->admin)->get('/stamp_correction_request/list'));

        $this->assertStringContainsString('承認済み1', $approved);
        $this->assertStringContainsString('承認済み2', $approved);
        $this->assertStringNotContainsString('承認待ちの申請', $approved);
    }

    public function test_修正申請の詳細内容が正しく表示されている(): void
    {
        $application = $this->makeApplication(User::factory()->create(['name' => '申請 太郎']), '2026-11-10', '打刻漏れのため');
        $application->update(['new_clock_in' => '08:30', 'new_clock_out' => '17:15']);
        ProposalBreak::factory()->for($application)->create(['break_in' => '12:30:00', 'break_out' => '13:20:00']);

        $this->actingAs($this->admin)->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertSee('value="申請 太郎"', false)
            ->assertSeeInOrder(['value="2026年"', 'value="11月10日"'], false)
            ->assertSee('value="08:30"', false)
            ->assertSee('value="17:15"', false)
            ->assertSee('value="12:30"', false)
            ->assertSee('value="13:20"', false)
            ->assertSee('打刻漏れのため')
            ->assertSee('<button class="applied-form__button--submit" type="submit">承認</button>', false);
    }

    public function test_修正申請の承認処理が正しく行われる(): void
    {
        $user = User::factory()->create();
        $application = $this->makeApplication($user, '2026-11-10', '電車遅延のため');
        $record = $application->attendanceRecord;
        AttendanceBreak::factory()->for($record)->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
        $application->update(['new_clock_in' => '09:30', 'new_clock_out' => '18:30']);
        ProposalBreak::factory()->for($application)->create(['break_in' => '12:15:00', 'break_out' => '13:15:00']);

        $this->actingAs($this->admin)->post('/stamp_correction_request/approve/'.$application->id)
            ->assertRedirect('/stamp_correction_request/approve/'.$application->id);

        $this->assertSame(Application::STATUS_APPROVED, $application->fresh()->approval_status);
        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'clock_in' => '09:30:00', 'clock_out' => '18:30:00', 'comment' => '電車遅延のため']);
        $this->assertSame(
            [['12:15:00', '13:15:00']],
            $record->breaks()->get(['break_in', 'break_out'])->map(fn (AttendanceBreak $break) => [$break->break_in, $break->break_out])->all()
        );
        $this->get('/stamp_correction_request/approve/'.$application->id)->assertSee('<p class="applied-form__item">承認済み</p>', false);

        // 一般ユーザーの画面にも反映される
        $this->actingAs($user)->get('/attendance/detail/'.$record->id)
            ->assertSee('name="new_clock_in" value="09:30"', false)
            ->assertSee('name="new_break_in[0]" value="12:15"', false);
    }
}
