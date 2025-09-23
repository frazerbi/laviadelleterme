<?php

declare(strict_types=1);

namespace TermeGestSetInfo\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetInfoVendutoResponse implements ResultInterface
{
    private ?string $GetInfoVendutoResult = null;

    public function getGetInfoVendutoResult(): ?string
    {
        return $this->GetInfoVendutoResult;
    }

    public function withGetInfoVendutoResult(?string $GetInfoVendutoResult): static
    {
        $new = clone $this;
        $new->GetInfoVendutoResult = $GetInfoVendutoResult;

        return $new;
    }
}
