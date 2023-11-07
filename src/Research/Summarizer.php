<?php

namespace Genstack\Research;

use Genstack\OpenAI\Facades\OpenAI;

class Summarizer
{
    const MODEL = 'gpt-3.5-turbo-16k';

    public function summarize(string $text, string $objective)
    {
        $detailedPrompt = file_get_contents(genstack_prompts_path('research/summarize.txt'));
        $detailedPrompt = str_replace('{{objective}}', $objective, $detailedPrompt);
        $detailedPrompt = str_replace('{{text}}', $text, $detailedPrompt);

        $response = OpenAI::chat()->create([
            'messages' => [
                ['role' => 'system', 'content' => 'You are a research assistant asked to provide detailed summaries.'],
                ['role' => 'user', 'content' => $detailedPrompt],
            ],
            'model' => self::MODEL,
            'temperature' => 0.6,
        ]);

        // Debugging output
        return $response->choices[0]->message->content;
    }
}
