<?php

namespace Genstack\Tests\Unit;

use Genstack\Rewriter\ContentRewriter;
use Genstack\Tests\TestCase;

class RewriterTest extends TestCase
{
    public function test_rewriter()
    {
        $rewriter = app(ContentRewriter::class);
        $content = 'This is a test. This is only a test. So, what can we all do with just a test? Let us see about that.';
        $rewrite = $rewriter->rewrite($content);

        $this->assertNotEmpty($rewrite);
        $this->assertNotEquals($content, $rewrite);

        // Compare the similarity using levenshtein distance
        $this->assertLessThan(50, levenshtein($content, $rewrite));

    }
}
