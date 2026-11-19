<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * テストケース ID11：勤怠詳細情報修正機能（一般ユーザー）
 */
class UserAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));
        $this->user = User::factory()->create(['name' => '申請 太郎']);
        $this->record = AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-10']);
        AttendanceBreak::factory()->for($this->record)->create();
    }

    /**
     * 修正フォームの入力値
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formData(array $overrides = []): array
    {
        return array_merge([
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00', ''],
            'new_break_out' => ['13:00', ''],
            'comment' => '電車遅延のため',
        ], $overrides);
    }

    /**
     * 勤怠詳細画面から修正申請を送信する
     *
     * @param  array<string, mixed>  $overrides
     */
    private function submit(AttendanceRecord $record, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->user)
            ->from('/attendance/detail/'.$record->id)
            ->post('/attendance/'.$record->id, $this->formData($overrides));
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

    public function test_出勤時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit($this->record, ['new_clock_in' => '19:00'])
            ->assertSessionHasErrors(['new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です']);
    }

    public function test_休憩開始時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit($this->record, ['new_break_in' => ['19:00', ''], 'new_break_out' => ['19:30', '']])
            ->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    public function test_休憩終了時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit($this->record, ['new_break_out' => ['18:30', '']])
            ->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    public function test_備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        $this->submit($this->record, ['comment' => ''])
            ->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    public function test_修正申請処理が実行される(): void
    {
        $this->submit($this->record, ['new_clock_out' => '18:30'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/attendance/detail/'.$this->record->id);

        $application = Application::firstOrFail();
        $this->assertSame(Application::STATUS_PENDING, $application->approval_status);
        $this->assertSame('18:30', $application->new_clock_out);

        $admin = User::factory()->admin()->create();
        [$pending] = $this->tabs($this->actingAs($admin)->get('/stamp_correction_request/list'));
        $this->assertStringContainsString('申請 太郎', $pending);
        $this->assertStringContainsString('電車遅延のため', $pending);

        $this->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertSee('申請 太郎')
            ->assertSee('value="18:30"', false)
            ->assertSee('電車遅延のため');
    }

    public function test_承認待ちにログインユーザーが行った申請が全て表示されていること(): void
    {
        $other = AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-09']);
        $this->submit($this->record, ['comment' => '1件目の申請']);
        $this->submit($other, ['comment' => '2件目の申請']);

        [$pending] = $this->tabs($this->actingAs($this->user)->get('/stamp_correction_request/list'));

        $this->assertStringContainsString('1件目の申請', $pending);
        $this->assertStringContainsString('2026/11/10', $pending);
        $this->assertStringContainsString('2件目の申請', $pending);
        $this->assertStringContainsString('2026/11/09', $pending);
    }

    public function test_承認済みに管理者が承認した修正申請が全て表示されている(): void
    {
        $this->submit($this->record, ['comment' => '承認される申請']);
        $application = Application::firstOrFail();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/stamp_correction_request/approve/'.$application->id);

        [$pending, $approved] = $this->tabs($this->actingAs($this->user)->get('/stamp_correction_request/list'));

        $this->assertStringContainsString('承認される申請', $approved);
        $this->assertStringNotContainsString('承認される申請', $pending);
    }

    public function test_各申請の詳細を押下すると勤怠詳細画面に遷移する(): void
    {
        $this->submit($this->record);
        $application = Application::firstOrFail();

        $this->actingAs($this->user)->get('/stamp_correction_request/list')
            ->assertSee('href="'.url('/application/'.$application->id).'">詳細', false);

        $this->get('/application/'.$application->id)->assertRedirect('/attendance/detail/'.$this->record->id);
        $this->get('/attendance/detail/'.$this->record->id)
            ->assertOk()
            ->assertSee('承認待ちのため修正はできません。');
    }
}
