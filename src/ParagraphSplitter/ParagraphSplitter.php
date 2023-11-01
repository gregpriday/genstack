<?php

namespace Genstack\ParagraphSplitter;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Exception\RequestException;
use GuzzleRetry\GuzzleRetryMiddleware;

class ParagraphSplitter
{
    protected string $baseUri = 'https://paragraph-split-3l2brtmjcq-uc.a.run.app';
    protected Client $client;

    public function __construct()
    {
        $stack = HandlerStack::create();
        $stack->push(GuzzleRetryMiddleware::factory([
            'retries_enabled' => true,
            'max_retry_attempts' => 5,
        ]));

        $this->client = new Client([
            'handler' => $stack,
            'base_uri' => $this->baseUri,
            'timeout'  => 60.0,  // Timeout in seconds.
        ]);
    }

    /**
     * Split solid text into paragraphs.
     *
     * @param string $text
     * @return string
     * @throws GuzzleException
     */
    public function splitParagraphs(string $text): string
    {
        $response = $this->client->post('/paragraphs', [
            'json' => ['text' => $text]
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * Split paragraphs into chapters (by splitting on '---').
     *
     * @param string $text
     * @return array
     * @throws GuzzleException
     */
    public function splitChapters(string $text): array
    {
        $response = $this->client->post('/chapters', [
            'json' => ['text' => $text]
        ]);
        $text = $response->getBody()->getContents();

        // Now split the text into chapters by splitting on '---'
        return array_map(function ($chapter) {
            return trim($chapter);
        }, explode("\n---\n", $text));
    }

    /**
     * Tokenize text using the GPT-3 tokenizer.
     *
     * @param string $text
     * @return array
     * @throws GuzzleException
     */
    public function tokenizeText(string $text): array
    {
        $response = $this->client->post('/tokenize', [
            'json' => ['text' => $text]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
