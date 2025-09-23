<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\RequestInterface;

class SetPrenotazione implements RequestInterface
{
    private bool $AllInclusive;

    private ?string $Categoria = null;

    private ?string $CodControllo = null;

    private ?string $Cognome = null;

    private ?string $Email = null;

    private ?string $Nome = null;

    private ?string $Note = null;

    private ?string $Provincia = null;

    private ?string $Telefono = null;

    private ?string $codice = null;

    private int $idDisponibilita;

    private ?string $protection = null;

    private bool $uomodonna;

    /**
     * Constructor
     */
    public function __construct(
        int $idDisponibilita,
        ?string $codice,
        ?string $Cognome,
        ?string $Nome,
        ?string $Telefono,
        ?string $Note,
        ?string $Provincia,
        bool $uomodonna,
        ?string $Email,
        bool $AllInclusive,
        ?string $Categoria,
        ?string $CodControllo,
        ?string $protection
    ) {
        $this->idDisponibilita = $idDisponibilita;
        $this->codice = $codice;
        $this->Cognome = $Cognome;
        $this->Nome = $Nome;
        $this->Telefono = $Telefono;
        $this->Note = $Note;
        $this->Provincia = $Provincia;
        $this->uomodonna = $uomodonna;
        $this->Email = $Email;
        $this->AllInclusive = $AllInclusive;
        $this->Categoria = $Categoria;
        $this->CodControllo = $CodControllo;
        $this->protection = $protection;
    }

    public function getAllInclusive(): bool
    {
        return $this->AllInclusive;
    }

    public function getCategoria(): ?string
    {
        return $this->Categoria;
    }

    public function getCodControllo(): ?string
    {
        return $this->CodControllo;
    }

    public function getCodice(): ?string
    {
        return $this->codice;
    }

    public function getCognome(): ?string
    {
        return $this->Cognome;
    }

    public function getEmail(): ?string
    {
        return $this->Email;
    }

    public function getIdDisponibilita(): int
    {
        return $this->idDisponibilita;
    }

    public function getNome(): ?string
    {
        return $this->Nome;
    }

    public function getNote(): ?string
    {
        return $this->Note;
    }

    public function getProtection(): ?string
    {
        return $this->protection;
    }

    public function getProvincia(): ?string
    {
        return $this->Provincia;
    }

    public function getTelefono(): ?string
    {
        return $this->Telefono;
    }

    public function getUomodonna(): bool
    {
        return $this->uomodonna;
    }

    public function withAllInclusive(bool $AllInclusive): static
    {
        $new = clone $this;
        $new->AllInclusive = $AllInclusive;

        return $new;
    }

    public function withCategoria(?string $Categoria): static
    {
        $new = clone $this;
        $new->Categoria = $Categoria;

        return $new;
    }

    public function withCodControllo(?string $CodControllo): static
    {
        $new = clone $this;
        $new->CodControllo = $CodControllo;

        return $new;
    }

    public function withCodice(?string $codice): static
    {
        $new = clone $this;
        $new->codice = $codice;

        return $new;
    }

    public function withCognome(?string $Cognome): static
    {
        $new = clone $this;
        $new->Cognome = $Cognome;

        return $new;
    }

    public function withEmail(?string $Email): static
    {
        $new = clone $this;
        $new->Email = $Email;

        return $new;
    }

    public function withIdDisponibilita(int $idDisponibilita): static
    {
        $new = clone $this;
        $new->idDisponibilita = $idDisponibilita;

        return $new;
    }

    public function withNome(?string $Nome): static
    {
        $new = clone $this;
        $new->Nome = $Nome;

        return $new;
    }

    public function withNote(?string $Note): static
    {
        $new = clone $this;
        $new->Note = $Note;

        return $new;
    }

    public function withProtection(?string $protection): static
    {
        $new = clone $this;
        $new->protection = $protection;

        return $new;
    }

    public function withProvincia(?string $Provincia): static
    {
        $new = clone $this;
        $new->Provincia = $Provincia;

        return $new;
    }

    public function withTelefono(?string $Telefono): static
    {
        $new = clone $this;
        $new->Telefono = $Telefono;

        return $new;
    }

    public function withUomodonna(bool $uomodonna): static
    {
        $new = clone $this;
        $new->uomodonna = $uomodonna;

        return $new;
    }
}
