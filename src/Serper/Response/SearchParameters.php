<?php

namespace Genstack\Serper\Response;

use Genstack\Traits\ArrayConstructable;

class SearchParameters
{
    use ArrayConstructable;

    /**
     * @var string The search query.
     */
    public readonly string $q;

    /**
     * @var int|null The number of results returned.
     */
    public readonly ?int $num;

    /**
     * SearchParameters constructor.
     *
     * @param string $q
     * @param int $num
     */
    public function __construct(
        string $q, int $num
    ) {
        $this->q = $q;
        $this->num = $num;
    }
}
