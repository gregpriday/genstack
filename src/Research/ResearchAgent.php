<?php

namespace Genstack\Research;

use Genstack\OpenAI\Facades\OpenAI;
use Genstack\Serper\SerperClient;
use Genstack\Zyte\ZyteClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class ResearchAgent
{
    const CACHE_DURATION = '7 days';

    private SerperClient $serper;
    private ZyteClient $zyte;
    private Summarizer $summarizer;
    private string $model;

    public function __construct(SerperClient $serper, ZyteClient $zyte, Summarizer $summarizer, string $model)
    {
        $this->serper = $serper;
        $this->zyte = $zyte;
        $this->summarizer = $summarizer;
        $this->model = $model;
    }

    /**
     * @param string $prompt The prompt of what we want the system to research.
     * @param int $maxDepth The maximum recursion depth for the research method.
     * @return null|string The response from the AI
     *
     * @throws GuzzleException
     */
    public function research(string $prompt, int $maxDepth = 2): ?string
    {
        // Get the content from the pages.
        $urls = $this->getUrlsToClick($prompt);
        if (empty($urls)) {
            return null;
        }

        $markdown = $this->getMarkdownFromCacheOrExtract($urls);

        // Prepare the information for the AI
        $information = collect($markdown)
            ->filter()
            ->map(function($content, $url) use ($prompt) {
                $content = $this->summarizer->summarize($content, $prompt);
                return "> {$url}\n\n---\n\n{$content}\n\n---\n\n";
            })
            ->join("\n");

        // Add the system and user's role messages.
        $functions = json_decode(file_get_contents(genstack_prompts_path('research/delegate-research.json')), true);
        $system = trim(file_get_contents(genstack_prompts_path('research/system-research.txt')));
        $system = str_replace('{{DATE}}', now()->format('d F Y'), $system);

        $instructions = trim(file_get_contents(genstack_prompts_path('research/instructions.txt')));
        $instructions = str_replace('{{PROMPT}}', $prompt, $instructions);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $information],
            ['role' => 'user', 'content' => $instructions]
        ];

        $args = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.15,
        ];
        if($maxDepth > 1) {
            $args = array_merge([
                'functions' => $functions,
            ], $args);
        }

        // Call the OpenAI API with the messages.
        $response = OpenAI::chat()->create($args);

        // Check if 'delegate_research' function is called by the AI and if the depth allows for further research.
        if ($response->choices[0]->message->functionCall?->name === 'delegate_research') {
            $arguments = json_decode($response->choices[0]->message->functionCall->arguments);
            $additionalResearch = [];

            // Record the function call to the message array.
            $messages[] = [
                'role' => 'assistant',
                'content' => null,
                'function_call' => ['name' => 'delegate_research', 'arguments' => json_encode($arguments)]
            ];

            $subPrompts = Arr::wrap($arguments->prompts);
            // Recursively call research for each sub-prompt from the delegate research.
            foreach ($subPrompts as $subPrompt) {
                $recursiveResponse = $this->research($subPrompt, $maxDepth - 1);
                if ($recursiveResponse) {
                    $additionalResearch[] = "# {$subPrompt}\n\n" . $recursiveResponse;
                }
            }
            $additionalResearchContent = implode("\n\n---\n\n", $additionalResearch);

            // ADd the additional research which the research agent can use to construct the final research.
            $messages[] = [
                'role' => 'function',
                'name' => 'delegate_research',
                'content' => $additionalResearchContent
            ];

            // Add the message to do the final research again.
            $messages[] = ['role' => 'user', 'content' => $instructions];

            // Call the OpenAI API again with updated messages after recursive research.
            $response = OpenAI::chat()
                ->create([
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.2,
                ]);
        }

        // Return the final research content.
        return $response->choices[0]->message->content ?? null;
    }

    /**
     * Get the URLs to click on from the search results.
     *
     * @param string $prompt
     * @return array
     * @throws GuzzleException
     */
    protected function getUrlsToClick(string $prompt): array
    {
        $messages = [];
        $system = trim(file_get_contents(genstack_prompts_path('research/system-fetch-results.txt')));
        $functions = json_decode(file_get_contents(genstack_prompts_path('research/search-functions.json')), true);

        $messages[] = ['role' => 'system', 'content' => $system];
        $messages[] = ['role' => 'system', 'content' => $prompt];

        $response = OpenAI::chat()
            ->create([
                'model' => $this->model,
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
                'model' => $this->model,
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

    /**
     * Filter out URLs we know we can't get from Zyte.
     *
     * @param array $urls
     * @return array
     */
    protected function filterUrls(array $urls): array
    {
        $blockedHosts = config('genstack.research.blocked_hosts');

        // Filter any URLs that have a blocked host
        return array_filter($urls, function ($url) use ($blockedHosts) {
            $host = parse_url($url, PHP_URL_HOST);
            return !in_array($host, $blockedHosts);
        });
    }

    /**
     * Extract markdown either from the URL, or from the cache.
     *
     * @param array $urls
     * @return array
     * @throws GuzzleException
     */
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
