<?php

namespace Genstack\OpenAI\Facades;

use Illuminate\Support\Facades\Facade;
use OpenAI\Laravel\Testing\OpenAIFake;

/**
 * @method static \OpenAI\Resources\Audio audio()
 * @method static \OpenAI\Resources\Chat chat()
 * @method static \OpenAI\Resources\Completions completions()
 * @method static \OpenAI\Resources\Embeddings embeddings()
 * @method static \OpenAI\Resources\Edits edits()
 * @method static \OpenAI\Resources\Files files()
 * @method static \OpenAI\Resources\FineTunes fineTunes()
 * @method static \OpenAI\Resources\Images images()
 * @method static \OpenAI\Resources\Models models()
 * @method static \OpenAI\Resources\Moderations moderations()
 */
final class OpenAI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'openai-retry';
    }

    public static function fake(array $responses = []): OpenAIFake /** @phpstan-ignore-line */
    {
        $fake = new OpenAIFake($responses);
        self::swap($fake);

        return $fake;
    }
}
