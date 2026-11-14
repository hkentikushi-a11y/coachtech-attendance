<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース ID1：認証機能（一般ユーザー）
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 会員登録フォームの入力値
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function formData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト 太郎',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }

    public function test_名前が未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->formData(['name' => '']));

        $response->assertSessionHasErrors(['name' => 'お名前を入力してください']);
    }

    public function test_メールアドレスが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->formData(['email' => '']));

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    public function test_パスワードが8文字未満の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->formData(['password' => 'pass123', 'password_confirmation' => 'pass123']));

        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
    }

    public function test_パスワードが一致しない場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->formData(['password_confirmation' => 'different']));

        $response->assertSessionHasErrors(['password_confirmation' => 'パスワードと一致しません']);
    }

    public function test_パスワードが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->formData(['password' => '', 'password_confirmation' => '']));

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_フォームに内容が入力されていた場合_データが正常に保存される(): void
    {
        $response = $this->post('/register', $this->formData());

        $response->assertRedirect('/attendance');
        $this->assertDatabaseHas('users', [
            'name' => 'テスト 太郎',
            'email' => 'test@example.com',
            'admin_status' => false,
        ]);
        $this->assertAuthenticated();
    }
}
