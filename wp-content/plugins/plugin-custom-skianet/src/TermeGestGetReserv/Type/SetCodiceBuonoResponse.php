<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class SetCodiceBuonoResponse implements ResultInterface
{
    private ?string $setCodiceBuonoResult = null;

    public function getSetCodiceBuonoResult(): ?string
    {
        return $this->setCodiceBuonoResult;
    }

    public function withSetCodiceBuonoResult(?string $setCodiceBuonoResult): static
    {
        $new = clone $this;
        $new->setCodiceBuonoResult = $setCodiceBuonoResult;

        return $new;
    }
}
