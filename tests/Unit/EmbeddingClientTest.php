<?php

namespace Genstack\Tests\Unit;

use Genstack\Embedding\EmbeddingClient;
use Genstack\Rewriter\ContentRewriter;
use Genstack\Tests\TestCase;

class EmbeddingClientTest extends TestCase
{
    public function test_rewriter()
    {
        $embedding = app(EmbeddingClient::class);
        $vector = $embedding->embed('This is a test');
        $vector = $vector->toArray();

        $this->assertIsArray($vector);
        $this->assertCount(768, $vector);
    }
}
