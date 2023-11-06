<?php

namespace Genstack\Rewriter;

use Genstack\OpenAI\Facades\OpenAI;
use Genstack\ParagraphSplitter\ParagraphSplitter;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;

class ContentRewriter
{
    private ParagraphSplitter $splitter;
    private string $model;
    private string $target;

    /**
     * ContentRewriter constructor.
     *
     * @param ParagraphSplitter $splitter The ParagraphSplitter instance.
     * @param string            $model    The model identifier for OpenAI chat completion.
     * @param string            $target   The target style or intent for rewriting. Should tie in with model training.
     */
    public function __construct(ParagraphSplitter $splitter, string $model, string $target)
    {
        $this->splitter = $splitter;
        $this->model = $model;
        $this->target = $target;
    }

    /**
     * Rewrites the given content by making a request to OpenAI's API.
     *
     * @param string $content The content to be rewritten.
     * @param float $temperature The creativity temperature for the rewrite.
     *
     * @return string The rewritten content.
     * @throws GuzzleException
     */
    public function rewrite(string $content, float $temperature = 0.2): string
    {
        // Split the content into parts or use as-is
        $contentParts = count(explode("\n\n", $content)) > 8 ? $this->splitter->splitChapters($content) : [$content];

        // Rewrite each part and join them back together
        return collect($contentParts)
            ->map(function ($part) use ($temperature) {
                // Make the API call using the OpenAI facade and get the rewritten content
                $response = OpenAI::chat()->create([
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->preparePrompt($part)],
                    ],
                    'temperature' => $temperature,
                ]);

                // Assuming the response format contains a 'choices' array
                return $response->choices[0]->message->content ?? '';
            })
            ->join("\n\n");
    }

    /**
     * Prepares the prompt for the given content.
     *
     * @param string $content The content we're rewriting.
     * @return string
     */
    protected function preparePrompt(string $content): string
    {
        $prompt = trim(file_get_contents(genstack_prompts_path('rewriter/instructions.txt')));
        $prompt = str_replace('{{CONTENT}}', $content, $prompt);
        return $prompt;
    }

    /**
     * @return string The system prompt for the rewrite.
     */
    protected function systemPrompt(): string
    {
        $prompt = trim(file_get_contents(genstack_prompts_path('rewriter/system.txt')));
        $prompt = str_replace('{{TARGET}}', $this->target, $prompt);
        return $prompt;
    }
}
