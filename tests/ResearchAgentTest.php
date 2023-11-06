<?php

namespace Genstack\Tests;

use Genstack\Research\ResearchAgent;

class ResearchAgentTest extends TestCase
{
    public function test_perform_research()
    {
        $agent = app(ResearchAgent::class);
        $research = $agent->research('what are some of the upcoming features in Laravel 11?');
        $this->assertNotEmpty($research);
    }
}
