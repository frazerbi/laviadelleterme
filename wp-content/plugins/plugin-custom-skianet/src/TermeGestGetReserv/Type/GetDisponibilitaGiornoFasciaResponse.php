<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetDisponibilitaGiornoFasciaResponse implements ResultInterface
{
    private ?GetDisponibilitaGiornoFasciaResult $GetDisponibilitaGiornoFasciaResult = null;

    public function getGetDisponibilitaGiornoFasciaResult(): ?GetDisponibilitaGiornoFasciaResult
    {
        return $this->GetDisponibilitaGiornoFasciaResult;
    }

    public function withGetDisponibilitaGiornoFasciaResult(?GetDisponibilitaGiornoFasciaResult $GetDisponibilitaGiornoFasciaResult): static
    {
        $new = clone $this;
        $new->GetDisponibilitaGiornoFasciaResult = $GetDisponibilitaGiornoFasciaResult;
        return $new;
    }
}