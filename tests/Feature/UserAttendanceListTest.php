<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース ID9：勤怠一覧情報取得機能（一般ユーザー）
 */
class UserAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));
        $this->user = User::factory()->create();
    }

    public function test_自分が行った勤怠情報が全て表示されている(): void
    {
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-02', 'clock_in' => '09:00:00', 'clock_out' => '18:00:00']);
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-04', 'clock_in' => '08:45:00', 'clock_out' => '17:30:00']);
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-10', 'clock_in' => '10:15:00', 'clock_out' => '19:20:00']);
        AttendanceRecord::factory()->create(['date' => '2026-11-03', 'clock_in' => '07:07:00', 'clock_out' => '16:16:00']);

        $response = $this->actingAs($this->user)->get('/attendance/list');

        $response->assertSeeInOrder(['11/02(月)', '09:00', '18:00', '11/04(水)', '08:45', '17:30', '11/10(火)', '10:15', '19:20']);
        $response->assertDontSee('07:07');
    }

    public function test_勤怠一覧画面に遷移した際に現在の月が表示される(): void
    {
        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee('<p class="current-month">2026/11</p>', false);
    }

    public function test_前月を押下した時に表示月の前月の情報が表示される(): void
    {
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-10-15', 'clock_in' => '08:30:00', 'clock_out' => '17:45:00']);

        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee('href="?date=2026-10"', false);

        $this->get('/attendance/list?date=2026-10')
            ->assertSee('<p class="current-month">2026/10</p>', false)
            ->assertSeeInOrder(['10/15(木)', '08:30', '17:45']);
    }

    public function test_翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-12-01', 'clock_in' => '09:30:00', 'clock_out' => '18:30:00']);

        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee('href="?date=2026-12"', false);

        $this->get('/attendance/list?date=2026-12')
            ->assertSee('<p class="current-month">2026/12</p>', false)
            ->assertSeeInOrder(['12/01(火)', '09:30', '18:30']);
    }

    public function test_詳細を押下するとその日の勤怠詳細画面に遷移する(): void
    {
        $record = AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-10']);

        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee('href="' . url('/attendance/' . $record->id) . '">詳細', false);

        $this->get('/attendance/' . $record->id)->assertRedirect('/attendance/detail/' . $record->id);
        $this->get('/attendance/detail/' . $record->id)->assertOk()->assertSee('11月10日');
    }
}
