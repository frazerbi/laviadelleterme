<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetAnnulliResponse implements ResultInterface
{
    private ?GetAnnulliResult $getAnnulliResult = null;

    public function getGetAnnulliResult(): ?GetAnnulliResult
    {
        return $this->getAnnulliResult;
    }

    public function withGetAnnulliResult(?GetAnnulliResult $getAnnulliResult): static
    {
        $new = clone $this;
        $new->getAnnulliResult = $getAnnulliResult;

        return $new;
    }
}
