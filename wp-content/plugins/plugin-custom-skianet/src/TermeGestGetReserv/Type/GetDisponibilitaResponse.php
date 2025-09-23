<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetDisponibilitaResponse implements ResultInterface
{
    private ?GetDisponibilitaResult $getDisponibilitaResult = null;

    public function getGetDisponibilitaResult(): ?GetDisponibilitaResult
    {
        return $this->getDisponibilitaResult;
    }

    public function withGetDisponibilitaResult(?GetDisponibilitaResult $getDisponibilitaResult): static
    {
        $new = clone $this;
        $new->getDisponibilitaResult = $getDisponibilitaResult;

        return $new;
    }
}
