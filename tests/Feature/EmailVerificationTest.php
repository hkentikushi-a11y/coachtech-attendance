<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * テストケース ID16：★ メール認証機能
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * メール認証用の署名付き URL を作る（認証メールのリンクと同じもの）
     */
    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
    }

    public function test_会員登録後_認証メールが送信される(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => '認証 太郎',
            'email' => 'verify@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/attendance');

        $user = User::where('email', 'verify@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/attendance')->assertRedirect('/email/verify');
    }

    public function test_メール認証誘導画面で認証はこちらからボタンを押下するとメール認証サイトに遷移する(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/email/verify')
            ->assertOk()
            ->assertSee('登録していただいたメールアドレスに認証メールを送付しました。')
            ->assertSee('href="http://localhost:8025"', false)
            ->assertSee('認証はこちらから');
    }

    public function test_メール認証サイトのメール認証を完了すると勤怠登録画面に遷移する(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verificationUrl($user))->assertRedirect('/attendance');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/attendance')->assertOk()->assertSee('<p class="attendance__status--item">勤務外</p>', false);
    }

    public function test_未認証のままログインするとメール認証誘導画面に移される(): void
    {
        User::factory()->unverified()->create(['email' => 'unverified@example.com']);

        $this->post('/login', ['email' => 'unverified@example.com', 'password' => 'password'])->assertRedirect('/attendance');

        $this->get('/attendance')->assertRedirect('/email/verify');
        $this->get('/attendance/list')->assertRedirect('/email/verify');
    }

    public function test_認証メールを再送するボタンで認証メールが再送される(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from('/email/verify')->post('/email/verification-notification')
            ->assertRedirect('/email/verify');

        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/email/verify')->assertSee('認証メールを再送しました。');
    }

    public function test_改ざんした認証リンクでは認証されない(): void
    {
        $user = User::factory()->unverified()->create();
        $url = str_replace('/email/verify/' . $user->id . '/', '/email/verify/' . $user->id . '/0', $this->verificationUrl($user));

        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
