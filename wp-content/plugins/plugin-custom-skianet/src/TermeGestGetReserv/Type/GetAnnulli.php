<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetAnnulli implements RequestInterface
{
    private ?string $protection = null;

    /**
     * Constructor
     */
    public function __construct(?string $protection)
    {
        $this->protection = $protection;
    }

    public function getProtection(): ?string
    {
        return $this->protection;
    }

    public function withProtection(?string $protection): static
    {
        $new = clone $this;
        $new->protection = $protection;

        return $new;
    }
}
