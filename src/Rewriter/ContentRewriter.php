<?php

namespace Genstack\Rewriter;

use Genstack\OpenAI\Facades\OpenAI;
use Genstack\ParagraphSplitter\ParagraphSplitter;
use Illuminate\Support\Collection;

class ContentRewriter
{
    const DEFAULT_MODEL = 'ft:gpt-3.5-turbo-0613:siteorigin::89vbQuR2';
    const DEFAULT_TARGET = 'the target style';

    private ParagraphSplitter $splitter;
    private string $model;
    private string $target;

    public function __construct(ParagraphSplitter $splitter, string $model = self::DEFAULT_MODEL, string $target = self::DEFAULT_TARGET)
    {
        $this->splitter = $splitter;
        $this->model = $model;
        $this->target = $target;
    }

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

    protected function preparePrompt(string $content): string
    {
        $prompt = trim(file_get_contents(genstack_prompts_path('rewriter/prompt.txt')));
        $prompt = str_replace('{{CONTENT}}', $content, $prompt);
        return $prompt;
    }

    protected function systemPrompt(): string
    {
        $prompt = trim(file_get_contents(genstack_prompts_path('rewriter/system.txt')));
        $prompt = str_replace('{{TARGET}}', $this->target, $prompt);
        return $prompt;
    }
}
