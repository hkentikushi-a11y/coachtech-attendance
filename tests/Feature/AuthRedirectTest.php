<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 認証まわりのリダイレクト（未ログイン・ログイン済み・ログアウト後・ロール違い）
 */
class AuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_未ログインで一般ユーザーの画面を開くとログイン画面へ移される(): void
    {
        foreach (['/attendance', '/attendance/list', '/attendance/report', '/stamp_correction_request/list'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_未ログインで管理者の画面を開くと管理者ログイン画面へ移される(): void
    {
        foreach (['/admin/attendance/list', '/admin/staff/list', '/stamp_correction_request/approve/1'] as $path) {
            $this->get($path)->assertRedirect('/admin/login');
        }
    }

    public function test_ログイン済みの一般ユーザーがログイン画面や会員登録画面を開くと勤怠登録画面へ移される(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect('/attendance');
        $this->get('/register')->assertRedirect('/attendance');
        $this->get('/admin/login')->assertRedirect('/attendance');
    }

    public function test_ログイン済みの管理者がログイン画面を開くと管理者の勤怠一覧へ移される(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/login')->assertRedirect('/admin/attendance/list');
        $this->get('/login')->assertRedirect('/admin/attendance/list');
        $this->get('/register')->assertRedirect('/admin/attendance/list');
    }

    public function test_一般ユーザーはログアウトするとログイン画面へ移され_保護された画面を開けなくなる(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->get('/attendance')->assertRedirect('/login');
    }

    public function test_管理者はログアウトすると管理者ログイン画面へ移され_保護された画面を開けなくなる(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/admin/logout')->assertRedirect('/admin/login');

        $this->assertGuest();
        $this->get('/admin/attendance/list')->assertRedirect('/admin/login');
    }

    public function test_一般ユーザーが管理者の画面を開くと勤怠登録画面へ戻される(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/attendance/list')->assertRedirect('/attendance');
        $this->get('/admin/staff/list')->assertRedirect('/attendance');
    }

    public function test_管理者が一般ユーザーの画面を開くと管理者の勤怠一覧へ移される(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/attendance')->assertRedirect('/admin/attendance/list');
        $this->get('/attendance/report')->assertRedirect('/admin/attendance/list');
    }

    public function test_管理者ログイン画面が表示される(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('管理者ログイン');
    }
}
