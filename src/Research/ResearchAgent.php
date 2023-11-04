<?php

namespace Genstack\Research;

use Genstack\OpenAI\Facades\OpenAI;
use Genstack\Serper\SerperClient;
use Genstack\Zyte\ZyteClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use Illuminate\Support\Facades\Cache;

class ResearchAgent
{
    const MODEL = 'gpt-4';
    const CACHE_DURATION = '1 day';

    private SerperClient $serper;
    private ZyteClient $zyte;
    private Summarizer $summarizer;

    public function __construct(SerperClient $serper, ZyteClient $zyte, Summarizer $summarizer)
    {
        $this->serper = $serper;
        $this->zyte = $zyte;
        $this->summarizer = $summarizer;
    }

    /**
     * @param string $prompt The prompt of what we want the system to research.
     * @return null|string The response from the AI
     */
    public function research(string $prompt): ?string
    {
        // Let's get the content of all the pages
        $urls = $this->getUrlsToClick($prompt);
        if(empty($urls)) {
            return null;
        }

        $markdown = $this->getMarkdownFromCacheOrExtract($urls);

        $information = collect($markdown)
            ->filter()
            ->map(function($content, $url) use ($prompt){
                $content = $this->summarizer->summarize($content, $prompt);
                return "> {$url}\n\n---\n\n{$content}\n\n---\n\n";
            })
            ->join("\n");

        $system = trim(file_get_contents(genstack_prompts_path('research/system-research.txt')));
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $system];
        $messages[] = ['role' => 'user', 'content' => $information];
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = OpenAI::chat()
            ->create([
                'model' => self::MODEL,
                'messages' => $messages,
                'temperature' => 0.2,
            ]);

        return $response->choices[0]->message->content;
    }

    protected function getUrlsToClick(string $prompt): array
    {
        $messages = [];
        $system = trim(file_get_contents(genstack_prompts_path('/research/system-fetch-results.txt')));
        $functions = json_decode(file_get_contents(genstack_prompts_path('/research/search-functions.json')), true);

        $messages[] = ['role' => 'system', 'content' => $system];
        $messages[] = ['role' => 'system', 'content' => $prompt];

        $response = OpenAI::chat()
            ->create([
                'model' => self::MODEL,
                'messages' => $messages,
                'functions' => $functions,
                'temperature' => 0.0,
                'function_call' => [
                    'name' => 'search_google',
                ]
            ]);

        $queries = json_decode($response->choices[0]->message->functionCall->arguments)->queries;
        $results = $this->serper->searchMulti($queries, 5);
        $results = json_decode(json_encode($results));

        // Clean up the results by removing all null values
        $results = array_map(function ($result) {
            $result->organic = array_map(function ($item) {
                return array_filter((array) $item, function ($value) {
                    return !empty($value);
                });
            }, $result->organic);
            return $result;
        }, $results);

        $messages[] = ['role' => 'assistant', 'content' => 'null', 'function_call' => ['name' => 'search_google', 'arguments' => $response->choices[0]->message->functionCall->arguments]];
        $messages[] = ['role' => 'function', 'name' => 'search_google', 'content' => json_encode($results)];

        // We need to decide which results to click on
        $response = OpenAI::chat()
            ->create([
                'model' => self::MODEL,
                'messages' => $messages,
                'functions' => $functions,
                'temperature' => 0.0,
                'function_call' => [
                    'name' => 'click_results',
                ]
            ]);

        $urls = json_decode($response->choices[0]->message->functionCall->arguments)->urls;
        $urls = $this->filterUrls($urls);

        // Return at most 6 results
        return array_slice($urls, 0, 5);
    }

    protected function filterUrls(array $urls): array
    {
        $blockedHosts = config('research.blocked_hosts');

        // Filter any URLs that have a blocked host
        return array_filter($urls, function ($url) use ($blockedHosts) {
            $host = parse_url($url, PHP_URL_HOST);
            return !in_array($host, $blockedHosts);
        });
    }

    /**
     * @param array|string $markdown
     * @param string $prompt The prompt to use for the extraction
     * @return array
     */
    protected function extractContentFromMarkdown(array|string $markdown, string $prompt): array
    {
        $return = [];
        foreach($markdown as $url => $content) {
            $extracted = trim($this->extractor->extract($prompt, $content));
            if($extracted !== 'FALSE') {
                $return[$url] = $extracted;
            }
        }

        return $return;
    }

    protected function getMarkdownFromCacheOrExtract(array $urls): array
    {
        $cachedMarkdown = [];
        $urlsNotInCache = [];

        foreach ($urls as $url) {
            if (Cache::has($url)) {
                $cachedMarkdown[$url] = Cache::get($url);
            } else {
                $urlsNotInCache[] = $url;
            }
        }

        $newMarkdown = $this->zyte->extractMarkdown($urlsNotInCache);


        foreach ($newMarkdown as $url => $content) {
            Cache::put($url, $content, now()->add(self::CACHE_DURATION)); // adjust the duration as needed
        }

        return $cachedMarkdown + $newMarkdown;
    }
}
