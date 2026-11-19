<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Notifications\Messages\MailMessage;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // 認証メールを日本語にする
        VerifyEmail::toMailUsing(fn (object $notifiable, string $url): MailMessage => (new MailMessage)
            ->subject('【coachtech 勤怠管理アプリ】メールアドレスの確認')
            ->greeting($notifiable->name . ' 様')
            ->line('会員登録ありがとうございます。下のボタンを押して、メールアドレスの確認を完了してください。')
            ->action('メールアドレスを確認する', $url)
            ->line('このメールに心当たりがない場合は、破棄してください。')
            ->salutation('coachtech 勤怠管理アプリ'));
    }
}
