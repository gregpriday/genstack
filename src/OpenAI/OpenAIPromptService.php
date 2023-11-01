<?php

namespace Genstack\OpenAI;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Facades\OpenAI;

abstract class OpenAIPromptService
{
    const DEFAULT_TEMPERATURE = 0.7;

    protected float|array $temperature = self::DEFAULT_TEMPERATURE;
    protected string $model = 'gpt-3.5-turbo';

    protected array $stages = [];
    private ?array $functions = null;

    public function __construct()
    {
        // Load functions.json if it exists
        $folder = Str::kebab(class_basename(get_called_class()));
        $functionsPath = resource_path("prompts/services/$folder/functions.json");
        if (file_exists($functionsPath)) {
            $this->functions = json_decode(file_get_contents($functionsPath), true);
        }
    }

    /**
     * Run the service with the given arguments to substitute.
     *
     * @param array $arguments
     * @param array $params Parameters to pass to .
     *
     * @return Collection
     */
    public function run(array $arguments = [], array $params = []): Collection
    {
        if (empty($this->stages)) {
            // Single-stage execution
            $arguments = $this->filterArguments($arguments);
            $messages = $this->messages(null, $arguments);
            $messages = $this->filterMessages($messages);
            return $this->getResponse($messages, null, $params);
        }

        // Multi-stage execution
        $messages = [];
        $responses = [];
        foreach($this->stages as $stage) {
            $arguments = $this->filterArguments($arguments, $stage, $messages);
            $messages = $this->messages($stage, $arguments, $messages);
            $messages = $this->filterMessages($messages, $stage);

            $response = $this->getResponse($messages, $stage, $params);

            $message = (array) $response->first()->message;
            unset($message['functionCall']);
            $messages[] = $message;

            $responses[$stage] = $response->first();

            if (!$this->shouldProceed($stage, $response->first())) {
                // If shouldProceed returns false, break out of the loop
                break;
            }
        }

        // Return all responses
        return collect($responses);
    }

    /**
     * Decide whether to proceed to the next stage or not.
     * Child classes can override this method to provide their own logic.
     *
     * @param string $stage
     * @param array $responses
     * @return bool
     */
    protected function shouldProceed(string $stage, $response): bool
    {
        return true; // By default, always proceed
    }

    public function setModel(string $model)
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Allows child classes to filter the messages before sending them to OpenAI.
     *
     * @param array $messages
     * @param string|null $stage
     * @return array
     */
    protected function filterMessages(array $messages, string $stage = null): array
    {
        return $messages;
    }

    /**
     * Allows child classes to filter the arguments before sending them to OpenAI.
     *
     * @param array $arguments
     * @param string|null $stage
     * @return array
     */
    protected function filterArguments(array $arguments, string $stage = null, array $messages = []): array
    {
        return $arguments;
    }

    /**
     * Get the system message for the given stage.
     *
     * @param string|null $stage The stage to get the system message for
     * @return string The system message.
     */
    protected function systemMessage(?string $stage = null): string
    {
        $folder = Str::kebab(class_basename(get_called_class()));

        $filename = $stage ?
            "prompts/services/$folder/system-$stage.md" :
            "prompts/services/$folder/system.md";

        return trim(file_get_contents(resource_path($filename)));
    }

    /**
     * Fetch examples if there are any.
     *
     * @return array
     */
    protected function fetchExamples(): array
    {
        $folder = Str::kebab(class_basename(get_called_class()));
        $path = resource_path("prompts/services/$folder/examples/");
        $exampleFiles = glob($path . 'example-*.md');

        $examples = [];
        foreach ($exampleFiles as $file) {
            $content = trim(file_get_contents($file));
            // Splitting by a predefined separator (let's assume "###")
            $parts = explode("###", $content);
            if (count($parts) == 2) {
                $examples[] = [
                    'user' => trim($parts[0]),
                    'assistant' => trim($parts[1])
                ];
            }
        }
        return $examples;
    }

    /**
     * Get the instructions for the given stage.
     *
     * @param array $arguments The arguments to pass to the instructions
     * @param string|null $stage The stage to get the instructions for
     * @return string The instructions.
     */
    protected function instructions(array $arguments = [], ?string $stage = null): string
    {
        $folder = Str::kebab(class_basename(get_called_class()));

        $filename = $stage ?
            "prompts/services/$folder/instructions-$stage.md" :
            "prompts/services/$folder/instructions.md";

        $instructions = trim(file_get_contents(resource_path($filename)));

        // Perform substitutions
        foreach ($arguments as $key => $value) {
            $instructions = str_replace("{{{$key}}}", $value, $instructions);
        }

        return $instructions;
    }

    /**
     * Get the messages for the given stage and join them with the current history.
     *
     * @param string|null $stage The stage to get the messages for
     * @param array $arguments The arguments to pass to the instructions
     * @param array $history The history of messages
     * @return array The messages.
     */
    protected function messages(?string $stage = null, array $arguments = [], array $history = []): array
    {
        // Filter out the old system message
        $history = array_filter($history, function($message) {
            return $message['role'] !== 'system';
        });

        // Construct the new messages
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $this->systemMessage($stage)];

        if(is_null($stage)) {
            $examples = $this->fetchExamples();
            foreach ($examples as $example) {
                $messages[] = ['role' => 'user', 'content' => $example['user']];
                $messages[] = ['role' => 'assistant', 'content' => $example['assistant']];
            }
        }

        // Merge old chat messages right after the system message
        $messages = array_merge($messages, $history);

        // Add the new instruction message
        $messages[] = ['role' => 'user', 'content' => $this->instructions($arguments, $stage)];

        return $messages;
    }

    /**
     * Call the actual OpenAI API and return the response.
     *
     * @param array $messages The messages to pass.
     * @param string|null $stage The stage to get the response for.
     * @return Collection The response.
     */
    protected function getResponse(array $messages, ?string $stage = null, array $params = []): Collection
    {
        $params = array_merge([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => is_array($this->temperature) ? $this->temperature[$stage] ?? self::DEFAULT_TEMPERATURE : $this->temperature,
        ], $params);

        // If functions are defined, include them in the API call
        if ($this->functions) {
            $params['functions'] = $this->functions;
        }

        $response = OpenAI::chat()->create($params);

        // Process the response choices
        return collect($response->choices)
            ->map(function ($choice) {
                // Handle function calls
                if ($choice->finishReason === 'function_call') {
                    // Parse the JSON from the function call
                    $choice->functionCall = (object) [
                        'name' => $choice->message->functionCall->name,
                        'arguments' => json_decode($choice->message->functionCall->arguments, true)
                    ];
                } else {
                    // Check for JSON content and add to the result if valid
                    $content = trim($choice->message->content);

                    // Check if content is wrapped in triple backticks
                    if (Str::startsWith($content, '```') && Str::endsWith($content, '```')) {
                        $content = trim($content, '` ');
                    }

                    if ($this->isValidJson($content)) {
                        $choice->message->json = json_decode($content, true);
                    }
                }

                return $choice;
            });
    }

    private function isValidJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
