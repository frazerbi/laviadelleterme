<?php

declare(strict_types=1);

namespace TermeGestSetInfo\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetInfoVenduto implements RequestInterface
{
    private ?string $codice = null;

    /**
     * Constructor
     */
    public function __construct(?string $codice)
    {
        $this->codice = $codice;
    }

    public function getCodice(): ?string
    {
        return $this->codice;
    }

    public function withCodice(?string $codice): static
    {
        $new = clone $this;
        $new->codice = $codice;

        return $new;
    }
}
