<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース ID4：日時取得機能
 */
class DateTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_現在の日時情報が画面と同じ形式で出力されている(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:30:00'));

        $response = $this->actingAs(User::factory()->create())->get('/attendance');

        $response->assertOk();
        $response->assertSee('value="2026年11月11日(水)"', false);
        $response->assertSee('value="10:30"', false);
    }
}
