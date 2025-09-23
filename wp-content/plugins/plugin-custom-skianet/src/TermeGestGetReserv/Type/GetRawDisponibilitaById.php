<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetRawDisponibilitaById implements RequestInterface
{
    private int $idDisponibilita;

    private ?string $protection = null;

    /**
     * Constructor
     */
    public function __construct(int $idDisponibilita, ?string $protection)
    {
        $this->idDisponibilita = $idDisponibilita;
        $this->protection = $protection;
    }

    public function getIdDisponibilita(): int
    {
        return $this->idDisponibilita;
    }

    public function getProtection(): ?string
    {
        return $this->protection;
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
