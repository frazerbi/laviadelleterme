<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use DateTimeInterface;
use Phpro\SoapClient\Type\RequestInterface;

class GetFascia implements RequestInterface
{
    private DateTimeInterface $data;

    private ?string $protection = null;

    /**
     * Constructor
     */
    public function __construct(DateTimeInterface $data, ?string $protection)
    {
        $this->data = $data;
        $this->protection = $protection;
    }

    public function getData(): DateTimeInterface
    {
        return $this->data;
    }

    public function getProtection(): ?string
    {
        return $this->protection;
    }

    public function withData(DateTimeInterface $data): static
    {
        $new = clone $this;
        $new->data = $data;

        return $new;
    }

    public function withProtection(?string $protection): static
    {
        $new = clone $this;
        $new->protection = $protection;

        return $new;
    }
}
