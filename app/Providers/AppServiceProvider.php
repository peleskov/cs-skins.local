<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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
        
        // Принудительно используем HTTPS для всех URL
        \URL::forceScheme('https');
        
        $socialite = $this->app->make('Laravel\Socialite\Contracts\Factory');
        $socialite->extend('steam', function ($app) use ($socialite) {
            $config = $app['config']['services.steam'];
            return $socialite->buildProvider(\SocialiteProviders\Steam\Provider::class, $config);
        });
    }
}
