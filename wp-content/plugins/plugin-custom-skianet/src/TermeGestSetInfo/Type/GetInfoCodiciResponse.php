<?php

declare(strict_types=1);

namespace TermeGestSetInfo\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetInfoCodiciResponse implements ResultInterface
{
    private ?string $GetInfoCodiciResult = null;

    public function getGetInfoCodiciResult(): ?string
    {
        return $this->GetInfoCodiciResult;
    }

    public function withGetInfoCodiciResult(?string $GetInfoCodiciResult): static
    {
        $new = clone $this;
        $new->GetInfoCodiciResult = $GetInfoCodiciResult;

        return $new;
    }
}
