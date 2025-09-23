<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetCodiciBigliettiResponse implements ResultInterface
{
    private ?GetCodiciBigliettiResult $getCodiciBigliettiResult = null;

    public function getGetCodiciBigliettiResult(): ?GetCodiciBigliettiResult
    {
        return $this->getCodiciBigliettiResult;
    }

    public function withGetCodiciBigliettiResult(?GetCodiciBigliettiResult $getCodiciBigliettiResult): static
    {
        $new = clone $this;
        $new->getCodiciBigliettiResult = $getCodiciBigliettiResult;

        return $new;
    }
}
