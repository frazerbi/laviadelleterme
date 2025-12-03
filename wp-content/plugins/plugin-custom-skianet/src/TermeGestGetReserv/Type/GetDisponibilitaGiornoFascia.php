<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetDisponibilitaGiornoFascia implements RequestInterface
{
    private int $anno;

    private int $giorno;

    private int $mese;

    private ?string $protection = null;

    /**
     * Constructor
     */
    public function __construct(int $anno, int $mese, int $giorno, ?string $protection)
    {
        $this->anno = $anno;
        $this->mese = $mese;
        $this->giorno = $giorno;
        $this->protection = $protection;
    }

    public function getAnno(): int
    {
        return $this->anno;
    }

    public function getGiorno(): int
    {
        return $this->giorno;
    }

    public function getMese(): int
    {
        return $this->mese;
    }

    public function getProtection(): ?string
    {
        return $this->protection;
    }

    public function withAnno(int $anno): static
    {
        $new = clone $this;
        $new->anno = $anno;

        return $new;
    }

    public function withGiorno(int $giorno): static
    {
        $new = clone $this;
        $new->giorno = $giorno;

        return $new;
    }

    public function withMese(int $mese): static
    {
        $new = clone $this;
        $new->mese = $mese;

        return $new;
    }

    public function withProtection(?string $protection): static
    {
        $new = clone $this;
        $new->protection = $protection;

        return $new;
    }
}