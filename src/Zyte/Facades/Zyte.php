<?php

namespace Genstack\Zyte\Facades;

use Genstack\Zyte\ZyteClient;
use Illuminate\Support\Facades\Facade;

class Zyte extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ZyteClient::class;
    }
}
