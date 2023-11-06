<?php

namespace Genstack\Zyte\Extractors;

use Explorer\Zyte\ZyteClient;
use Illuminate\Support\Facades\Cache;

class Extractor
{
    protected string $html;

    public function __construct(string $html)
    {
        $this->html = $html;
    }
}
