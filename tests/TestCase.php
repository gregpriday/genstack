<?php

namespace Genstack\Tests;

use Genstack\GenstackServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            GenstackServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // Load the config file
        $app['config']->set('genstack', require __DIR__.'/../config/genstack.php');
        $app['config']->set('openai', require __DIR__.'/../config/openai.php');
    }
}
