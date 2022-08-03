<?php

namespace amoori\LaravelExchangeRates\Classes;

use amoori\LaravelExchangeRates\Exceptions\InvalidCurrencyException;
use amoori\LaravelExchangeRates\Exceptions\InvalidDateException;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Contracts\Container\BindingResolutionException;

class ExchangeRate
{
    /**
     * The object used for making requests to the currency
     * conversion API.
     *
     * @var RequestBuilder
     */
    private $requestBuilder;

    /**
     * @var CacheRepository
     */
    private $cacheRepository;

    /**
     * @var bool
     */
    private $shouldBustCache = false;

    /**
     * ExchangeRate constructor.
     *
     * @param  RequestBuilder|null  $requestBuilder
     * @param  CacheRepository|null  $cacheRepository
     * @throws BindingResolutionException
     */
    public function __construct(RequestBuilder $requestBuilder = null, CacheRepository $cacheRepository = null)
    {
        $this->requestBuilder = $requestBuilder ?? new RequestBuilder(new Client());
        $this->cacheRepository = $cacheRepository ?? new CacheRepository();
    }

    /**
     * Return an array of available currencies that
     * can be used with this package.
     *
     * @param  array  $currencies
     *
     * @return array
     */
    public function currencies(array $currencies = []): array
    {
        $cacheKey = 'currencies';

        if ($cachedExchangeRate = $this->attemptToResolveFromCache($cacheKey)) {
            return $cachedExchangeRate;
        }

        $response = $this->requestBuilder->makeRequest('/latest', []);

        $currencies[] = $response['base'];

        foreach ($response['rates'] as $currency => $rate) {
            $currencies[] = $currency;
        }

        $this->cacheRepository->storeInCache($cacheKey, $currencies);

        return $currencies;
    }

    /**
     * Return the exchange rate between the $from and $to
     * parameters. If no $date parameter is passed, we
     * use today's date instead.
     *
     * @param  string  $from
     * @param  string  $to
     * @param  Carbon|null  $date
     *
     * @return string
     * @throws InvalidDateException
     *
     * @throws InvalidCurrencyException
     */
    public function exchangeRate(string $from, string $to, ?Carbon $date = null): string
    {
        Validation::validateCurrencyCode($from);
        Validation::validateCurrencyCode($to);

        if ($date) {
            Validation::validateDate($date);
        }

        if ($from === $to) {
            return 1.0;
        }

        $cacheKey = $this->cacheRepository->buildCacheKey($from, $to, $date ?? now());

        // if ($cachedExchangeRate = $this->attemptToResolveFromCache($cacheKey)) {
        //     return $cachedExchangeRate;
        // }

        if ($date) {
            $exchangeRate = $this->requestBuilder->makeRequest('/'.$date->format('Y-m-d'),
                ['base' => $from])['rates'][$to];
        } else {
            $exchangeRate = $this->requestBuilder->makeRequest('/latest', ['base' => $from])['rates'][$to];
        }

        $this->cacheRepository->storeInCache($cacheKey, $exchangeRate);

        return $exchangeRate;
    }

    /**
     * Return the exchange rates between the given
     * date range.
     *
     * @param  string  $from
     * @param  string  $to
     * @param  Carbon  $date
     * @param  Carbon  $endDate
     * @param  array  $conversions
     *
     * @return array
     * @throws Exception
     */
    public function exchangeRateBetweenDateRange(
        string $from,
        string $to,
        Carbon $date,
        Carbon $endDate,
        array $conversions = []
    ) {
        Validation::validateCurrencyCode($from);
        Validation::validateCurrencyCode($to);
        Validation::validateStartAndEndDates($date, $endDate);

        $cacheKey = $this->cacheRepository->buildCacheKey($from, $to, $date, $endDate);

        if ($cachedExchangeRate = $this->attemptToResolveFromCache($cacheKey)) {
            return $cachedExchangeRate;
        }

        if ($from === $to) {
            $conversions = $this->exchangeRateDateRangeResultWithSameCurrency($date, $endDate, $conversions);
        } else {
            $result = $this->requestBuilder->makeRequest('/history', [
                'base'     => $from,
                'start_at' => $date->format('Y-m-d'),
                'end_at'   => $endDate->format('Y-m-d'),
                'symbols'  => $to,
            ]);

            foreach ($result['rates'] as $date => $rate) {
                $conversions[$date] = $rate[$to];
            }

            ksort($conversions);
        }

        $this->cacheRepository->storeInCache($cacheKey, $conversions);

        return $conversions;
    }

    /**
     * Return the converted values between the $from and $to
     * parameters. If no $date parameter is passed, we
     * use today's date instead.
     *
     * @param  int  $value
     * @param  string  $from
     * @param  string  $to
     * @param  Carbon|null  $date
     *
     * @return float
     * @throws InvalidDateException
     *
     * @throws InvalidCurrencyException
     */
    public function convert(int $value, string $from, string $to, Carbon $date = null): float
    {
        return (float) $this->exchangeRate($from, $to, $date) * $value;
    }

    /**
     * Return an array of the converted values between
     * the given date range.
     *
     * @param  int  $value
     * @param  string  $from
     * @param  string  $to
     * @param  Carbon  $date
     * @param  Carbon  $endDate
     * @param  array  $conversions
     *
     * @return array
     * @throws Exception
     */
    public function convertBetweenDateRange(
        int $value,
        string $from,
        string $to,
        Carbon $date,
        Carbon $endDate,
        array $conversions = []
    ): array {
        foreach ($this->exchangeRateBetweenDateRange($from, $to, $date, $endDate) as $date => $exchangeRate) {
            $conversions[$date] = (float) $exchangeRate * $value;
        }

        ksort($conversions);

        return $conversions;
    }

    /**
     * If the 'from' and 'to' currencies are the same, we
     * don't need to make a request to the API. Instead,
     * we can build the response ourselves to improve
     * the performance.
     *
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     * @param  array  $conversions
     * @return array
     */
    private function exchangeRateDateRangeResultWithSameCurrency(
        Carbon $startDate,
        Carbon $endDate,
        array $conversions = []
    ): array {
        for ($date = clone $startDate; $date->lte($endDate); $date->addDay()) {
            if ($date->isWeekday()) {
                $conversions[$date->format('Y-m-d')] = 1.0;
            }
        }

        return $conversions;
    }

    /**
     * Determine whether if the cached result (if it
     * exists) should be deleted. This will force
     * a new exchange rate to be fetched from
     * the API.
     *
     * @param  bool  $bustCache
     * @return $this
     */
    public function shouldBustCache(bool $bustCache = true): self
    {
        $this->shouldBustCache = $bustCache;

        return $this;
    }

    /**
     * Attempt to fetch an item (more than likely an
     * exchange rate) from the cache. If it exists,
     * return it. If it has been specified, bust
     * the cache.
     *
     * @param  string  $cacheKey
     * @return mixed
     */
    private function attemptToResolveFromCache(string $cacheKey)
    {
        if ($this->shouldBustCache) {
            $this->cacheRepository->forget($cacheKey);
            $this->shouldBustCache = false;
        } elseif ($cachedValue = $this->cacheRepository->getFromCache($cacheKey)) {
            return $cachedValue;
        }
    }
}
