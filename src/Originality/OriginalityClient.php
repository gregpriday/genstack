<?php

namespace Genstack\Originality;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use stdClass;

class OriginalityClient
{
    const ENDPOINT = 'https://api.originality.ai/api/v1/';

    private Client $client;
    private string $apiKey;
    private string $aiModelVersion;

    public function __construct(string $apiKey, string $aiModelVersion = '2.0')
    {
        $this->apiKey = $apiKey;
        $this->aiModelVersion = $aiModelVersion;
        $this->client = new Client([
            'base_uri' => self::ENDPOINT,
            'headers' => [
                'Accept' => 'application/json',
                'X-OAI-API-KEY' => $this->apiKey,
            ],
        ]);
    }

    /**
     * Scan content for originality.
     *
     * @param string $content
     * @param string|null $title
     * @param bool $storeScan
     * @return stdClass|null
     * @throws GuzzleException
     */
    public function scanAi(string $content, string $title = null, bool $storeScan = false): ?stdClass
    {
        $body = [
            'content' => $content,
            'aiModelVersion' => $this->aiModelVersion,
            'storeScan' => $storeScan ? "true" : "false",
        ];

        if ($title) {
            $body['title'] = $title;
        }

        $response = $this->client->post('scan/ai', ['json' => $body]);
        return json_decode($response->getBody()->getContents());
    }
}
