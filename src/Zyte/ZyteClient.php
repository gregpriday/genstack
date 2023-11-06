<?php

namespace Genstack\Zyte;

use Genstack\Zyte\Extractors\CleanHtmlExtractor;
use Genstack\Zyte\Extractors\MarkdownExtractor;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleRetry\GuzzleRetryMiddleware;
use Illuminate\Support\Arr;

class ZyteClient
{
    const CONCURRENCY = 3;
    const API_ENDPOINT = 'https://api.zyte.com/v1/extract';

    private Client $client;

    private string $apiKey;

    private string $endpoint;

    public function __construct(string $apiKey, string $endpoint = null)
    {
        // Set the API key from the parameter or from a configuration/environment variable
        $this->apiKey = $apiKey;
        $this->endpoint = $endpoint ?? self::API_ENDPOINT;

        // Create a Guzzle handler stack and add the retry middleware
        $stack = HandlerStack::create();
        $stack->push(GuzzleRetryMiddleware::factory([
            'retries_enabled' => true,
            'max_retry_attempts' => 5,
        ]));

        // Initialize the Guzzle client with auth configuration and handler stack
        $this->client = new Client([
            'base_uri' => $this->endpoint,
            'headers' => ['Accept-Encoding' => 'gzip'],
            'auth' => [$apiKey, ''],
            'handler' => $stack,
        ]);
    }

    protected function requestFactory(array $body): Request
    {
        return new Request(
            'POST',
            self::API_ENDPOINT,
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip',
                'Authorization' => 'Basic '.base64_encode($this->apiKey.':'),
            ],
            json_encode($body)
        );
    }

    /**
     * Extract data from URLs using callback for processing.
     *
     * @param array $urls The URLs to extract from
     * @param callable|null $processCallback A callback to process each response
     * @param array $additionalArgs Additional request parameters
     * @return array
     */
    protected function extract(array $urls, callable $processCallback = null, array $additionalArgs = []): array
    {
        $requests = function ($urls, $additionalArgs) {
            foreach ($urls as $url) {
                yield function() use ($url, $additionalArgs) {
                    return $this->client->sendAsync($this->requestFactory(array_merge([
                        'url' => $url,
                    ], $additionalArgs)));
                };
            }
        };

        $responses = [];

        $pool = new Pool($this->client, $requests($urls, $additionalArgs), [
            'concurrency' => self::CONCURRENCY,
            'fulfilled' => function ($response, $index) use (&$responses, $urls, $processCallback) {
                $data = $response->getBody()->getContents();

                // If a callback for processing data is provided, use it
                $responses[$urls[$index]] = $processCallback ? $processCallback($data) : $data;
            },
            'rejected' => function ($reason, $index) use (&$responses, $urls) {
                $responses[$urls[$index]] = 'Error: ' . $reason->getMessage();
            }
        ]);

        // Initiate the transfers and create a promise
        $promise = $pool->promise();

        // Force the pool of requests to complete.
        $promise->wait();

        return $responses;
    }

    /**
     * Extract the HTML from a URL.
     *
     * @param string|array $url The URL to extract from
     * @return string|array
     */
    public function extractHttpBody(string|array $url): string|array
    {
        $returnSingle = is_string($url);
        $urls = Arr::wrap($url);

        $result = $this->extract($urls, function($data) {
            $decodedData = json_decode($data);
            return base64_decode($decodedData->httpResponseBody ?? '');
        }, ['httpResponseBody' => true]);

        return $returnSingle ? $result[$urls[0]] : $result;
    }


    /**
     * Extract the HTML from URLs using the browser engine.
     *
     * @param string|array $url The URL(s) to extract from
     * @return string|array
     */
    public function extractBrowserHtml(string|array $url): string|array
    {
        $returnSingle = is_string($url);
        $urls = Arr::wrap($url);

        $result = $this->extract($urls, function($data) {
            $decodedData = json_decode($data);
            return $decodedData->browserHtml ?? '';
        }, ['browserHtml' => true]);

        return $returnSingle ? $result[$urls[0]] : $result;
    }

    /**
     * Extract an article using the Zyte API.
     *
     * @param string|array $url The URL(s) to extract from
     * @return string|array
     */
    public function extractArticle(string|array $url): string|array
    {
        $returnSingle = is_string($url);
        $urls = Arr::wrap($url);

        $result = $this->extract($urls, function($data) {
            return json_decode($data);
        }, ['article' => true]);

        return $returnSingle ? $result[$urls[0]] : $result;
    }

    /**
     * Extract cleaned HTML from a URL.
     *
     * @param string|array $url The URL(s) to extract from
     * @param bool $browser Whether to use the browser engine or not
     * @return string|array The cleaned HTML
     */
    public function extractCleanHtml(string|array $url, bool $browser = true): string|array
    {
        $returnSingle = is_string($url);
        $urls = Arr::wrap($url);

        $result = $this->extract($urls, function($data) use ($browser) {
            $html = $browser ? (json_decode($data)->browserHtml ?? '') : $data;
            $extractor = new CleanHtmlExtractor($html);
            return $extractor->cleanHtml();
        }, $browser ? ['browserHtml' => true] : []);

        return $returnSingle ? $result[$urls[0]] : $result;
    }

    /**
     * Extract markdown from a URL.
     *
     * @param string|array $url The URL(s) to extract from
     * @param bool $browser Whether to use the browser engine or not
     * @return string|array The markdown
     */
    public function extractMarkdown(string|array $url, bool $browser = true): string|array
    {
        $returnSingle = is_string($url);
        $urls = Arr::wrap($url);

        $result = $this->extract($urls, function($data) use ($browser) {
            $html = $browser ? (json_decode($data)->browserHtml ?? '') : $data;
            $extractor = new MarkdownExtractor($html);
            return $extractor->extractMarkdown();
        }, $browser ? ['browserHtml' => true] : []);

        return $returnSingle ? $result[$urls[0]] : $result;
    }
}
