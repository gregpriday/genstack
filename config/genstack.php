<?php

return [
    'serper' => [
        'key' => env('SERPER_KEY'),
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

    'research' => [
        'blocked_hosts' => [
            'linkedin.com'
        ]
    ]
];
