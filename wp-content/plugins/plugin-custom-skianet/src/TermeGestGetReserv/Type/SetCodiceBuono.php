<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use DateTimeInterface;
use Phpro\SoapClient\Type\RequestInterface;

class SetCodiceBuono implements RequestInterface
{
    private ?string $categoria = null;

    private DateTimeInterface $datascadenza;

    private ?string $descrizione = null;

    private float $importo;

    private ?string $note = null;

    private ?string $protection = null;

    private ?string $utente = null;

    /**
     * Constructor
     */
    public function __construct(?string $categoria, float $importo, ?string $descrizione, ?string $utente, DateTimeInterface $datascadenza, ?string $note, ?string $protection)
    {
        $this->categoria = $categoria;
        $this->importo = $importo;
        $this->descrizione = $descrizione;
        $this->utente = $utente;
        $this->datascadenza = $datascadenza;
        $this->note = $note;
        $this->protection = $protection;
    }

    public function getCategoria(): ?string
    {
        return $this->categoria;
    }

    public function getDatascadenza(): DateTimeInterface
    {
        return $this->datascadenza;
    }

    public function getDescrizione(): ?string
    {
        return $this->descrizione;
    }

    public function getImporto(): float
    {
        return $this->importo;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getProtection(): ?string
    {
        return $this->protection;
    }

    public function getUtente(): ?string
    {
        return $this->utente;
    }

    public function withCategoria(?string $categoria): static
    {
        $new = clone $this;
        $new->categoria = $categoria;

        return $new;
    }

    public function withDatascadenza(DateTimeInterface $datascadenza): static
    {
        $new = clone $this;
        $new->datascadenza = $datascadenza;

        return $new;
    }

    public function withDescrizione(?string $descrizione): static
    {
        $new = clone $this;
        $new->descrizione = $descrizione;

        return $new;
    }

    public function withImporto(float $importo): static
    {
        $new = clone $this;
        $new->importo = $importo;

        return $new;
    }

    public function withNote(?string $note): static
    {
        $new = clone $this;
        $new->note = $note;

        return $new;
    }

    public function withProtection(?string $protection): static
    {
        $new = clone $this;
        $new->protection = $protection;

        return $new;
    }

    public function withUtente(?string $utente): static
    {
        $new = clone $this;
        $new->utente = $utente;

        return $new;
    }
}
