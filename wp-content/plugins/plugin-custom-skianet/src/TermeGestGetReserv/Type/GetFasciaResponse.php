<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetFasciaResponse implements ResultInterface
{
    private ?GetFasciaResult $getFasciaResult = null;

    public function getGetFasciaResult(): ?GetFasciaResult
    {
        return $this->getFasciaResult;
    }

    public function withGetFasciaResult(?GetFasciaResult $getFasciaResult): static
    {
        $new = clone $this;
        $new->getFasciaResult = $getFasciaResult;

        return $new;
    }
}
