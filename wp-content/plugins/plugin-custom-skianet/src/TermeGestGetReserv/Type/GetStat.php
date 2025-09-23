<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetStat implements RequestInterface
{
    private ?string $codici = null;

    private ?string $protection = null;

    /**
     * Constructor
     */
    public function __construct(?string $codici, ?string $protection)
    {
        $this->codici = $codici;
        $this->protection = $protection;
    }

    public function getCodici(): ?string
    {
        return $this->codici;
    }

    public function getProtection(): ?string
    {
        return $this->protection;
    }

    public function withCodici(?string $codici): static
    {
        $new = clone $this;
        $new->codici = $codici;

        return $new;
    }

    public function withProtection(?string $protection): static
    {
        $new = clone $this;
        $new->protection = $protection;

        return $new;
    }
}
