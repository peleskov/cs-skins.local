<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Order;
use App\Models\TradeOffer;
use App\Observers\OrderObserver;
use App\Observers\TradeOfferObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрируем команды консоли
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\PackExtensionCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Регистрируем Observer'ы
        Order::observe(OrderObserver::class);
        TradeOffer::observe(TradeOfferObserver::class);

        // Политика для Activity (модель из стороннего пакета — авторазрешение не работает)
        Gate::policy(\Spatie\Activitylog\Models\Activity::class, \App\Policies\ActivityPolicy::class);

        // Настройка Rate Limiting для уведомлений
        $this->configureRateLimiting();

        // Принудительно используем HTTPS для всех URL
        \URL::forceScheme('https');

        $socialite = $this->app->make('Laravel\Socialite\Contracts\Factory');
        $socialite->extend('steam', function ($app) use ($socialite) {
            $config = $app['config']['services.steam'];
            return $socialite->buildProvider(\SocialiteProviders\Steam\Provider::class, $config);
        });
    }

    /**
     * Настройка лимитов для очередей уведомлений
     */
    protected function configureRateLimiting(): void
    {
        // Telegram API лимиты: 30 сообщений в секунду, но ограничиваем до 25 для безопасности
        RateLimiter::for('telegram-notifications', function () {
            return Limit::perSecond(25)->by('telegram');
        });

        // Email лимиты: 100 писем в минуту (можно настроить под ваш SMTP)
        RateLimiter::for('email-notifications', function () {
            return Limit::perMinute(100)->by('email');
        });

        // GET-запросы (просмотр страниц, списки, детали) — общий лимит
        RateLimiter::for('api-read', function ($request) {
            $key = $request->user()?->id ?: $request->ip();
            return Limit::perMinute(120)->by('api-read|' . $key);
        });

        // POST-запросы (действия: продажа, добавление в корзину и т.д.)
        RateLimiter::for('api-action', function ($request) {
            $key = $request->user()?->id ?: $request->ip();
            return Limit::perMinute(30)->by('api-action|' . $key);
        });

        // Покупка/открытие кейсов — свой отдельный лимит
        RateLimiter::for('case-purchase', function ($request) {
            $key = $request->user()?->id ?: $request->ip();
            return Limit::perMinute(10)->by('case-purchase|' . $key);
        });

        // Критические действия (создание заказов, платежи)
        RateLimiter::for('api-critical', function ($request) {
            $key = $request->user()?->id ?: $request->ip();
            return Limit::perMinute(10)->by('api-critical|' . $key);
        });
    }
}
