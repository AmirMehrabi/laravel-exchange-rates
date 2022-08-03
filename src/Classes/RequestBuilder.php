<?php

namespace amoori\LaravelExchangeRates\Classes;

use GuzzleHttp\Client;

class RequestBuilder
{
    /** @var string */

    /**
     * @var Client
     */
    private $client;

    /**
     * RequestBuilder constructor.
     *
     * @param  Client|null  $client
     */
    public function __construct(Client $client = null)
    {
        $this->client = $client ?? new Client();
        $this->apiKey = env('EXCHANGE_RATES_API_KEY');
        $this->baseUrl = env('EXCHANGE_RATES_API_URL');
    }

    /**
     * Make an API request to the ExchangeRatesAPI.
     *
     * @param  string  $path
     * @param  array  ...$queryParams
     *
     * @return mixed
     */
    public function makeRequest(string $path, array $queryParams = [])
    {
        $url = $this->baseUrl.$path.'?access_key='.$this->apiKey;

        foreach ($queryParams as $param => $value) {
            $url .= '&'.urlencode($param).'='.urlencode($value);
        }

        return json_decode($this->client->get($url)->getBody()->getContents(), true);
    }
}
