<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetReservationResponse implements ResultInterface
{
    private ?GetReservationResult $getReservationResult = null;

    public function getGetReservationResult(): ?GetReservationResult
    {
        return $this->getReservationResult;
    }

    public function withGetReservationResult(?GetReservationResult $getReservationResult): static
    {
        $new = clone $this;
        $new->getReservationResult = $getReservationResult;

        return $new;
    }
}
