<?php

namespace amoori\LaravelExchangeRates\Providers;

use amoori\LaravelExchangeRates\Classes\ExchangeRate;
use Illuminate\Support\ServiceProvider;

class ExchangeRatesProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->alias(ExchangeRate::class, 'exchange-rate');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
    }
}
