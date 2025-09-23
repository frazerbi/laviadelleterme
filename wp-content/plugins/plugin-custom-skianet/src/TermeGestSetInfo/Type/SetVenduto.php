<?php

declare(strict_types=1);

namespace TermeGestSetInfo\Type;

use Phpro\SoapClient\Type\RequestInterface;

class SetVenduto implements RequestInterface
{
    private ?string $codice = null;

    private ?string $email = null;

    private ?string $nome = null;

    private float $prezzo;

    private ?string $security = null;

    /**
     * Constructor
     */
    public function __construct(?string $codice, float $prezzo, ?string $nome, ?string $email, ?string $security)
    {
        $this->codice = $codice;
        $this->prezzo = $prezzo;
        $this->nome = $nome;
        $this->email = $email;
        $this->security = $security;
    }

    public function getCodice(): ?string
    {
        return $this->codice;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getNome(): ?string
    {
        return $this->nome;
    }

    public function getPrezzo(): float
    {
        return $this->prezzo;
    }

    public function getSecurity(): ?string
    {
        return $this->security;
    }

    public function withCodice(?string $codice): static
    {
        $new = clone $this;
        $new->codice = $codice;

        return $new;
    }

    public function withEmail(?string $email): static
    {
        $new = clone $this;
        $new->email = $email;

        return $new;
    }

    public function withNome(?string $nome): static
    {
        $new = clone $this;
        $new->nome = $nome;

        return $new;
    }

    public function withPrezzo(float $prezzo): static
    {
        $new = clone $this;
        $new->prezzo = $prezzo;

        return $new;
    }

    public function withSecurity(?string $security): static
    {
        $new = clone $this;
        $new->security = $security;

        return $new;
    }
}
