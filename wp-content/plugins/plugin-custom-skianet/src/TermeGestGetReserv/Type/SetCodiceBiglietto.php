<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\RequestInterface;

class SetCodiceBiglietto implements RequestInterface
{
    private ?string $codice = null;

    private ?string $protection = null;

    private ?string $tipo = null;

    private ?string $utente = null;

    /**
     * Constructor
     */
    public function __construct(?string $codice, ?string $tipo, ?string $utente, ?string $protection)
    {
        $this->codice = $codice;
        $this->tipo = $tipo;
        $this->utente = $utente;
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

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function getUtente(): ?string
    {
        return $this->utente;
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

    public function withTipo(?string $tipo): static
    {
        $new = clone $this;
        $new->tipo = $tipo;

        return $new;
    }

    public function withUtente(?string $utente): static
    {
        $new = clone $this;
        $new->utente = $utente;

        return $new;
    }
}
