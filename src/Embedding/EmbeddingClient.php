<?php

namespace Genstack\Embedding;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Pgvector\Laravel\Vector;

class EmbeddingClient
{
    private Client $client;
    private string $accountId;
    private string $authToken;
    private string $size;

    const SIZE_SMALL = 'small';
    const SIZE_MEDIUM = 'base';
    const SIZE_LARGE = 'large';

    const SMALL_DIMENSIONS = 384;
    const MEDIUM_DIMENSIONS = 768;
    const LARGE_DIMENSIONS = 1024;

    /**
     * @param string $accountId The Cloudflare account ID.
     * @param string $authToken The Cloudflare API token.
     * @param string $size The size of the embedding to use. Either 'small', 'medium', or 'large'.
     */
    public function __construct(string $accountId, string $authToken, string $size = self::SIZE_MEDIUM)
    {
        $this->accountId = $accountId;
        $this->authToken = $authToken;
        $this->size = $size;

        $this->client = new Client();
    }

    public function dimensions(string $size = null): int
    {
        $size = $size ?? $this->size;
        return match ($size) {
            self::SIZE_SMALL => self::SMALL_DIMENSIONS,
            self::SIZE_MEDIUM => self::MEDIUM_DIMENSIONS,
            self::SIZE_LARGE => self::LARGE_DIMENSIONS,
            default => throw new Exception('Invalid embedding size'),
        };
    }

    private function endpoint(string $size = null): string
    {
        $size = $size ?? $this->size;
        return "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/ai/run/@cf/baai/bge-{$size}-en-v1.5";
    }

    /**
     * @param string $text The text to embed.
     * @param string $type The type of embedding to perform. Either 'passage' or 'query'.
     * @return Vector
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function embed(string $text, string $type = 'passage', string $size = null): Vector
    {
        if ($type === 'query') {
            $text = "Represent this sentence for searching relevant passages: {$text}";
        }

        $response = $this->client->post('', [
            'headers' => $this->getHeaders(),
            'json' => [
                'text' => $text,
            ],
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            throw new Exception("Request failed with status: $statusCode, Message: " . (string) $response->getBody());
        }

        $responseData = json_decode($response->getBody());

        // Return the embedding in Vector format
        return new Vector($responseData->result->data[0]);
    }

    /**
     * @param string $query The short query to embed as a vector.
     * @return Vector
     * @throws GuzzleException
     */
    public function embedQuery(string $query, string $size = null): Vector
    {
        return $this->embed($query, 'query', $size);
    }

    /**
     * @param string $passage The longer passage text to embed into a vector.
     * @return Vector
     * @throws GuzzleException
     */
    public function embedPassage(string $passage, string $size = null): Vector
    {
        return $this->embed($passage, 'passage', $size);
    }

    private function getHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->authToken,
        ];
    }
}
