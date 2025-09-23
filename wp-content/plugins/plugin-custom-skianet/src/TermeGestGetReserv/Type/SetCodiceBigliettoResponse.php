<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class SetCodiceBigliettoResponse implements ResultInterface
{
    private bool $setCodiceBigliettoResult;

    public function getSetCodiceBigliettoResult(): bool
    {
        return $this->setCodiceBigliettoResult;
    }

    public function withSetCodiceBigliettoResult(bool $setCodiceBigliettoResult): static
    {
        $new = clone $this;
        $new->setCodiceBigliettoResult = $setCodiceBigliettoResult;

        return $new;
    }
}
