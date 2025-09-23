<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\RequestInterface;

class SetReserv implements RequestInterface
{
    private ?Codeandid $codeandid = null;

    private ?string $protection = null;

    /**
     * Constructor
     */
    public function __construct(?Codeandid $codeandid, ?string $protection)
    {
        $this->codeandid = $codeandid;
        $this->protection = $protection;
    }

    public function getCodeandid(): ?Codeandid
    {
        return $this->codeandid;
    }

    public function getProtection(): ?string
    {
        return $this->protection;
    }

    public function withCodeandid(?Codeandid $codeandid): static
    {
        $new = clone $this;
        $new->codeandid = $codeandid;

        return $new;
    }

    public function withProtection(?string $protection): static
    {
        $new = clone $this;
        $new->protection = $protection;

        return $new;
    }
}
