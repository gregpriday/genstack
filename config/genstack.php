<?php

return [
    'serper' => [
        'key' => env('SERPER_API_KEY'),
    ],
    'zyte' => [
        'key' => env('ZYTE_API_KEY'),
    ],
    'originality' => [
        'key' => env('ORIGINALITY_API_KEY'),
        'model' => '2.0',
    ],
    'rewriter' => [
        'model' => env('REWRITER_MODEL', 'ft:gpt-3.5-turbo-0613:siteorigin::89vbQuR2'),
        'target' => env('REWRITER_TARGET_STYLE', 'a CNET Technology writer'),
    ],
    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'embedding_size' => env('CLOUDFLARE_EMBEDDING_SIZE', 'base'),
    ],

    'research' => [
        'model' => env('GENSTACK_RESEARCH_MODEL', 'gpt-4-1106-preview'),
        'blocked_hosts' => [
            'linkedin.com'
        ],
    ]
];
