<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetStatResponse implements ResultInterface
{
    private ?GetStatResult $GetStatResult = null;

    public function getGetStatResult(): ?GetStatResult
    {
        return $this->GetStatResult;
    }

    public function withGetStatResult(?GetStatResult $GetStatResult): static
    {
        $new = clone $this;
        $new->GetStatResult = $GetStatResult;

        return $new;
    }
}
