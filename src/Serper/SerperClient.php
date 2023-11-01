<?php

namespace Genstack\Serper;

use Genstack\Serper\Response\SerperResults;
use GuzzleHttp\Client;

class SerperClient
{
    private const SERPER_ENDPOINT = 'https://google.serper.dev/search';

    private string $key;

    private Client $client;

    public function __construct(string $key, string $endpoint = null)
    {
        $this->key = $key;
        $this->endpoint = $endpoint ?? self::SERPER_ENDPOINT;

        $this->client = new Client([
            'base_uri' => $this->endpoint,
            'headers' => [
                'X-API-KEY' => $this->key,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * @param  string  $query The query to perform
     * @param  int  $count The number of results to return
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function search(string $query, int $count = 10): SerperResults
    {
        $body = json_encode([
            'q' => $query,
            'num' => min($count, 100),  // Ensure it does not exceed 100
        ]);

        $response = $this->client->post('', ['body' => $body]);

        $result = json_decode($response->getBody(), true);

        return SerperResults::fromArray($result);
    }

    /**
     * @param  array  $queries The queries to perform. Up to 20 at once.
     * @param  int  $count The number of results to return per query
     * @return SerperResults[]
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function searchMulti(array $queries, int $count = 10): array
    {
        // Pre-process the queries and ensure the count does not exceed the limit
        $processedQueries = array_map(function ($query) use ($count) {
            return [
                'q' => $query,
                'num' => min($count, 100),
            ];
        }, $queries);

        // Encode the array of queries as JSON
        $body = json_encode($processedQueries);

        // Make the request and retrieve the response
        $response = $this->client->post('', ['body' => $body]);

        $results = json_decode($response->getBody(), true);

        // Convert the results to SerperResults objects
        return array_map(function ($result) {
            return SerperResults::fromArray($result);
        }, $results);
    }
}
