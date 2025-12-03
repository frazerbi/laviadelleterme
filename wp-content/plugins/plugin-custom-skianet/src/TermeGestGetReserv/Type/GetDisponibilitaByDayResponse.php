<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetDisponibilitaByDayResponse implements ResultInterface
{
    private ?GetDisponibilitaByDayResult $GetDisponibilitaByDayResult = null;

    public function getGetDisponibilitaByDayResult(): ?GetDisponibilitaByDayResult
    {
        return $this->GetDisponibilitaByDayResult;
    }

    public function withGetDisponibilitaByDayResult(?GetDisponibilitaByDayResult $GetDisponibilitaByDayResult): static
    {
        $new = clone $this;
        $new->GetDisponibilitaByDayResult = $GetDisponibilitaByDayResult;

        return $new;
    }
}