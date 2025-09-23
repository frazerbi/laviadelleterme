<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetDisponibilita implements RequestInterface
{
    private int $anno;

    private ?string $categoria = null;

    private int $mese;

    private ?string $protection = null;

    /**
     * Constructor
     */
    public function __construct(int $anno, int $mese, ?string $categoria, ?string $protection)
    {
        $this->anno = $anno;
        $this->mese = $mese;
        $this->categoria = $categoria;
        $this->protection = $protection;
    }

    public function getAnno(): int
    {
        return $this->anno;
    }

    public function getCategoria(): ?string
    {
        return $this->categoria;
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

    public function withCategoria(?string $categoria): static
    {
        $new = clone $this;
        $new->categoria = $categoria;

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
