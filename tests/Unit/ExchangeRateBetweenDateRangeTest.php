<?php

namespace amoori\LaravelExchangeRates\Tests\Unit;

use amoori\LaravelExchangeRates\Classes\ExchangeRate;
use amoori\LaravelExchangeRates\Classes\RequestBuilder;
use amoori\LaravelExchangeRates\Exceptions\InvalidCurrencyException;
use amoori\LaravelExchangeRates\Exceptions\InvalidDateException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;

class ExchangeRateBetweenDateRangeTest extends TestCase
{
    /** @test */
    public function exchange_rates_between_date_range_are_returned_if_exchange_rates_are_not_cached()
    {
        $fromDate = now()->subWeek();
        $toDate = now();

        $requestBuilderMock = Mockery::mock(RequestBuilder::class)->makePartial();
        $requestBuilderMock->expects('makeRequest')
            ->withArgs([
                '/history',
                [
                    'base'     => 'GBP',
                    'start_at' => $fromDate->format('Y-m-d'),
                    'end_at'   => $toDate->format('Y-m-d'),
                    'symbols'  => 'EUR',
                ],
            ])
            ->once()
            ->andReturn($this->mockResponse());

        $exchangeRate = new ExchangeRate($requestBuilderMock);
        $currencies = $exchangeRate->exchangeRateBetweenDateRange('GBP', 'EUR', $fromDate, $toDate);

        $expectedArray = [
            '2019-11-08' => 1.1606583254,
            '2019-11-06' => 1.1623446817,
            '2019-11-07' => 1.1568450522,
            '2019-11-05' => 1.1612648497,
            '2019-11-04' => 1.1578362356,
        ];

        $this->assertEquals($expectedArray, $currencies);
        $this->assertEquals($expectedArray,
            Cache::get('laravel_xr_GBP_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d')));
    }

    /** @test */
    public function cached_exchange_rates_are_returned_if_they_exist()
    {
        $fromDate = now()->subWeek();
        $toDate = now();

        $cacheKey = 'laravel_xr_GBP_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d');
        $cachedValues = $expectedArray = [
            '2019-11-08' => 1,
            '2019-11-06' => 2,
            '2019-11-07' => 3,
            '2019-11-05' => 4,
            '2019-11-04' => 5,
        ];
        Cache::forever($cacheKey, $cachedValues);

        $requestBuilderMock = Mockery::mock(RequestBuilder::class)->makePartial();
        $requestBuilderMock->expects('makeRequest')->never();

        $exchangeRate = new ExchangeRate($requestBuilderMock);
        $currencies = $exchangeRate->exchangeRateBetweenDateRange('GBP', 'EUR', $fromDate, $toDate);

        $this->assertEquals($expectedArray, $currencies);
        $this->assertEquals($expectedArray,
            Cache::get('laravel_xr_GBP_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d')));
    }

    /** @test */
    public function cached_exchange_rates_are_ignored_if_should_bust_cache_method_is_called()
    {
        $fromDate = now()->subWeek();
        $toDate = now();

        $cacheKey = 'GBP_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d');
        $cachedValues = [
            '2019-11-08' => 1,
            '2019-11-06' => 2,
            '2019-11-07' => 3,
            '2019-11-05' => 4,
            '2019-11-04' => 5,
        ];
        Cache::forever($cacheKey, $cachedValues);

        $requestBuilderMock = Mockery::mock(RequestBuilder::class)->makePartial();
        $requestBuilderMock->expects('makeRequest')
            ->withArgs([
                '/history',
                [
                    'base'     => 'GBP',
                    'start_at' => $fromDate->format('Y-m-d'),
                    'end_at'   => $toDate->format('Y-m-d'),
                    'symbols'  => 'EUR',
                ],
            ])
            ->once()
            ->andReturn($this->mockResponse());

        $exchangeRate = new ExchangeRate($requestBuilderMock);
        $currencies = $exchangeRate->shouldBustCache()->exchangeRateBetweenDateRange('GBP', 'EUR', $fromDate, $toDate);

        $expectedArray = [
            '2019-11-08' => 1.1606583254,
            '2019-11-06' => 1.1623446817,
            '2019-11-07' => 1.1568450522,
            '2019-11-05' => 1.1612648497,
            '2019-11-04' => 1.1578362356,
        ];

        $this->assertEquals($expectedArray, $currencies);
        $this->assertEquals($expectedArray,
            Cache::get('laravel_xr_GBP_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d')));
    }

    /** @test */
    public function request_is_not_made_if_the_currencies_are_the_same()
    {
        $fromDate = Carbon::createFromDate(2019, 11, 4);
        $toDate = Carbon::createFromDate(2019, 11, 10);

        $requestBuilderMock = Mockery::mock(RequestBuilder::class)->makePartial();
        $requestBuilderMock->expects('makeRequest')->withAnyArgs()->never();

        $exchangeRate = new ExchangeRate($requestBuilderMock);
        $currencies = $exchangeRate->exchangeRateBetweenDateRange('EUR', 'EUR', $fromDate, $toDate);

        $expectedArray = [
            '2019-11-08' => 1.0,
            '2019-11-06' => 1.0,
            '2019-11-07' => 1.0,
            '2019-11-05' => 1.0,
            '2019-11-04' => 1.0,
        ];

        $this->assertEquals($expectedArray, $currencies);

        $this->assertEquals($expectedArray,
            Cache::get('laravel_xr_EUR_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d')));
    }

    /** @test */
    public function exception_is_thrown_if_the_date_parameter_passed_is_in_the_future()
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('The date must be in the past.');

        $exchangeRate = new ExchangeRate();
        $exchangeRate->exchangeRateBetweenDateRange('EUR', 'GBP', now()->addMinute(), now()->subDay());
    }

    /** @test */
    public function exception_is_thrown_if_the_end_date_parameter_passed_is_in_the_future()
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('The date must be in the past.');

        $exchangeRate = new ExchangeRate();
        $exchangeRate->exchangeRateBetweenDateRange('EUR', 'GBP', now()->subDay(), now()->addMinute());
    }

    /** @test */
    public function exception_is_thrown_if_the_end_date_is_before_the_start_date()
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage("The 'from' date must be before the 'to' date.");

        $exchangeRate = new ExchangeRate();
        $exchangeRate->exchangeRateBetweenDateRange('EUR', 'GBP', now()->subDay(), now()->subWeek());
    }

    /** @test */
    public function exception_is_thrown_if_the_from_parameter_is_invalid()
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('INVALID is not a valid country code.');

        $exchangeRate = new ExchangeRate();
        $exchangeRate->exchangeRateBetweenDateRange('GBP', 'INVALID', now()->subWeek(), now()->subDay());
    }

    /** @test */
    public function exception_is_thrown_if_the_to_parameter_is_invalid()
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('INVALID is not a valid country code.');

        $exchangeRate = new ExchangeRate();
        $exchangeRate->exchangeRateBetweenDateRange('INVALID', 'GBP', now()->subWeek(), now()->subDay());
    }

    private function mockResponse()
    {
        return [
            'rates'    => [
                '2019-11-08' => [
                    'EUR' => 1.1606583254,
                ],
                '2019-11-06' => [
                    'EUR' => 1.1623446817,
                ],
                '2019-11-07' => [
                    'EUR' => 1.1568450522,
                ],
                '2019-11-05' => [
                    'EUR' => 1.1612648497,
                ],
                '2019-11-04' => [
                    'EUR' => 1.1578362356,
                ],
            ],
            'start_at' => '2019-11-03',
            'base'     => 'GBP',
            'end_at'   => '2019-11-10',
        ];
    }
}
