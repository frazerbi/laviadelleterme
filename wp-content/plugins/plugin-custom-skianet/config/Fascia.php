<?php

declare(strict_types=1);

namespace TermeGest\Type;

use stdClass;

class Fascia extends stdClass
{
    public string $fascia = '';

    public function getFascia(): string
    {
        return trim(str_ireplace('Ingresso ore', '', $this->fascia));
    }
}
