<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetRawDisponibilitaByIdResponse implements ResultInterface
{
    private int $getRawDisponibilitaByIdResult;

    public function getGetRawDisponibilitaByIdResult(): int
    {
        return $this->getRawDisponibilitaByIdResult;
    }

    public function withGetRawDisponibilitaByIdResult(int $getRawDisponibilitaByIdResult): static
    {
        $new = clone $this;
        $new->getRawDisponibilitaByIdResult = $getRawDisponibilitaByIdResult;

        return $new;
    }
}
