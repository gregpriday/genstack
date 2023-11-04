<?php

if(!function_exists('genstack_prompts_path')) {
    function genstack_prompts_path(string $path = ''): string
    {
        return realpath(__DIR__ . '/../prompts/' . $path);
    }
}
