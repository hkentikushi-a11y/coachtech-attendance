<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 開発・テスト中は、一覧で取得したモデルのリレーションを遅延読み込みした時点で例外にし、N+1 を見逃さない
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
