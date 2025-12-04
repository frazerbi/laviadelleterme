<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetDisponibilitaGiornoFasciaResponse implements ResultInterface
{
    private ?GetDisponibilitaGiornoFasciaResult $getDisponibilitaGiornoFasciaResult = null;

    public function getGetDisponibilitaGiornoFasciaResult(): ?GetDisponibilitaGiornoFasciaResult
    {
        return $this->getDisponibilitaGiornoFasciaResult;
    }

    public function withGetDisponibilitaGiornoFasciaResult(?GetDisponibilitaGiornoFasciaResult $getDisponibilitaGiornoFasciaResult): static
    {
        $new = clone $this;
        $new->getDisponibilitaGiornoFasciaResult = $getDisponibilitaGiornoFasciaResult;
        return $new;
    }
}