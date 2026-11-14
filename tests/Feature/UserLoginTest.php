<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース ID2：ログイン認証機能（一般ユーザー）
 */
class UserLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['email' => 'user@example.com']);
    }

    public function test_メールアドレスが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/login', ['email' => '', 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    public function test_パスワードが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/login', ['email' => 'user@example.com', 'password' => '']);

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_登録内容と一致しない場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/login', ['email' => 'wrong@example.com', 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }

    public function test_管理者のアカウントでは一般ユーザーとしてログインできない(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $response = $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }

    public function test_正しい情報でログインすると勤怠登録画面へ遷移する(): void
    {
        $response = $this->post('/login', ['email' => 'user@example.com', 'password' => 'password']);

        $response->assertRedirect('/attendance');
        $this->assertAuthenticated();
    }
}
