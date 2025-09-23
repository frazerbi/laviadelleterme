<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetDisponibilitaById implements RequestInterface
{
    private ?string $categoria = null;

    private int $idDisponibilita;

    private ?string $protection = null;

    /**
     * Constructor
     */
    public function __construct(int $idDisponibilita, ?string $categoria, ?string $protection)
    {
        $this->idDisponibilita = $idDisponibilita;
        $this->categoria = $categoria;
        $this->protection = $protection;
    }

    public function getCategoria(): ?string
    {
        return $this->categoria;
    }

    public function getIdDisponibilita(): int
    {
        return $this->idDisponibilita;
    }

    public function getProtection(): ?string
    {
        return $this->protection;
    }

    public function withCategoria(?string $categoria): static
    {
        $new = clone $this;
        $new->categoria = $categoria;

        return $new;
    }

    public function withIdDisponibilita(int $idDisponibilita): static
    {
        $new = clone $this;
        $new->idDisponibilita = $idDisponibilita;

        return $new;
    }

    public function withProtection(?string $protection): static
    {
        $new = clone $this;
        $new->protection = $protection;

        return $new;
    }
}
