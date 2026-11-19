<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース ID14：ユーザー情報取得機能（管理者）
 */
class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));
        $this->admin = User::factory()->admin()->create(['name' => '管理 者', 'email' => 'admin@example.com']);
        $this->staff = User::factory()->create(['name' => '社員 一郎', 'email' => 'staff1@example.com']);
    }

    public function test_管理者ユーザーが全一般ユーザーの氏名とメールアドレスを確認できる(): void
    {
        User::factory()->create(['name' => '社員 二郎', 'email' => 'staff2@example.com']);

        $this->actingAs($this->admin)->get('/admin/staff/list')
            ->assertOk()
            ->assertSeeInOrder(['社員 一郎', 'staff1@example.com', '社員 二郎', 'staff2@example.com'])
            ->assertDontSee('admin@example.com');
    }

    public function test_ユーザーの勤怠情報が正しく表示される(): void
    {
        AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-11-02', 'clock_in' => '09:10:00', 'clock_out' => '18:20:00']);
        AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-11-05', 'clock_in' => '08:55:00', 'clock_out' => '17:35:00']);
        AttendanceRecord::factory()->create(['date' => '2026-11-03', 'clock_in' => '07:07:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertOk()
            ->assertSee('社員 一郎さんの勤怠')
            ->assertSee('<p class="current-month">2026/11</p>', false)
            ->assertSeeInOrder(['11/02(月)', '09:10', '18:20', '11/05(木)', '08:55', '17:35'])
            ->assertDontSee('07:07');
    }

    public function test_前月を押下した時に表示月の前月の情報が表示される(): void
    {
        AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-10-20', 'clock_in' => '08:20:00', 'clock_out' => '17:20:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('href="?date=2026-10"', false);

        $this->get('/admin/attendance/staff/'.$this->staff->id.'?date=2026-10')
            ->assertSee('<p class="current-month">2026/10</p>', false)
            ->assertSeeInOrder(['10/20(火)', '08:20', '17:20']);
    }

    public function test_翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-12-03', 'clock_in' => '09:40:00', 'clock_out' => '18:40:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('href="?date=2026-12"', false);

        $this->get('/admin/attendance/staff/'.$this->staff->id.'?date=2026-12')
            ->assertSee('<p class="current-month">2026/12</p>', false)
            ->assertSeeInOrder(['12/03(木)', '09:40', '18:40']);
    }

    public function test_詳細を押下するとその日の勤怠詳細画面に遷移する(): void
    {
        $record = AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-11-10']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('href="'.url('/attendance/'.$record->id).'">詳細', false);

        $this->get('/attendance/'.$record->id)->assertRedirect('/admin/attendance/'.$record->id);
        $this->get('/admin/attendance/'.$record->id)->assertOk()->assertSee('11月10日');
    }
}
