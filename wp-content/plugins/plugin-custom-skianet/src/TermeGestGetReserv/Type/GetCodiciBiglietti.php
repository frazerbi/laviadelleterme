<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetCodiciBiglietti implements RequestInterface
{
    private ?string $codice = null;

    private ?string $protection = null;

    /**
     * Constructor
     */
    public function __construct(?string $codice, ?string $protection)
    {
        $this->codice = $codice;
        $this->protection = $protection;
    }

    public function getCodice(): ?string
    {
        return $this->codice;
    }

    public function getProtection(): ?string
    {
        return $this->protection;
    }

    public function withCodice(?string $codice): static
    {
        $new = clone $this;
        $new->codice = $codice;

        return $new;
    }

    public function withProtection(?string $protection): static
    {
        $new = clone $this;
        $new->protection = $protection;

        return $new;
    }
}
