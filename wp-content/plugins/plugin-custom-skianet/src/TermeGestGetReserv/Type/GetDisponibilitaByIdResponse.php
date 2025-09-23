<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetDisponibilitaByIdResponse implements ResultInterface
{
    private int $getDisponibilitaByIdResult;

    public function getGetDisponibilitaByIdResult(): int
    {
        return $this->getDisponibilitaByIdResult;
    }

    public function withGetDisponibilitaByIdResult(int $getDisponibilitaByIdResult): static
    {
        $new = clone $this;
        $new->getDisponibilitaByIdResult = $getDisponibilitaByIdResult;

        return $new;
    }
}
