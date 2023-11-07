<?php

namespace Genstack;

use Genstack\Embedding\EmbeddingClient;
use Genstack\Originality\OriginalityClient;
use Genstack\ParagraphSplitter\ParagraphSplitter;
use Genstack\Research\ResearchAgent;
use Genstack\Research\Summarizer;
use Genstack\Rewriter\ContentRewriter;
use Genstack\Serper\SerperClient;
use Genstack\Zyte\ZyteClient;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;
use OpenAI;
use OpenAI\Client as OpenAIClient;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Genstack\Genstack\Commands\GenstackCommand;

class GenstackServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('genstack')
            ->hasConfigFile()
            ->hasCommands([]);
    }

    public function packageBooted()
    {
        // We need an OpenAI client that retries
        $this->app->singleton('openai-retry', static function (): OpenAIClient {
            $apiKey = config('openai.api_key');
            $organization = config('openai.organization');

            // Add the retry middleware
            $stack = HandlerStack::create();
            $stack->push(GuzzleRetryMiddleware::factory(config('openai.retry', [])));

            // Create the client
            $client = new Client([
                'timeout' => config('openai.request_timeout', 30),
                'handler' => $stack,
            ]);

            return OpenAI::factory()
                ->withApiKey($apiKey)
                ->withOrganization($organization)
                ->withHttpClient($client)
                ->make();
        });

        $this->app->singleton(SerperClient::class, function(){
            return new SerperClient(config('genstack.serper.key'));
        });

        $this->app->singleton(ZyteClient::class, function(){
            return new ZyteClient(config('genstack.zyte.key'));
        });

        $this->app->singleton(ParagraphSplitter::class, function(){
            return new ParagraphSplitter();
        });

        $this->app->singleton(ContentRewriter::class, function(){
            return new ContentRewriter(
                app(ParagraphSplitter::class),
                config('genstack.rewriter.model'),
                config('genstack.rewriter.target'),
            );
        });
        $this->app->singleton(EmbeddingClient::class, function(){
            return new EmbeddingClient(
                config('genstack.cloudflare.account_id'),
                config('genstack.cloudflare.api_token'),
                config('genstack.cloudflare.embedding_size')
            );
        });
        $this->app->singleton(ResearchAgent::class, function(){
            return new ResearchAgent(
                app(SerperClient::class),
                app(ZyteClient::class),
                app(Summarizer::class),
                config('genstack.research.model')
            );
        });

        $this->app->singleton(OriginalityClient::class, function(){
            return new OriginalityClient(
                config('genstack.originality.key'),
                config('genstack.originality.model')
            );
        });
    }
}
