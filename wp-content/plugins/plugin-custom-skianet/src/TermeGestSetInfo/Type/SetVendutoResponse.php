<?php

declare(strict_types=1);

namespace TermeGestSetInfo\Type;

use Phpro\SoapClient\Type\ResultInterface;

class SetVendutoResponse implements ResultInterface
{
    private ?string $SetVendutoResult = null;

    public function getSetVendutoResult(): ?string
    {
        return $this->SetVendutoResult;
    }

    public function withSetVendutoResult(?string $SetVendutoResult): static
    {
        $new = clone $this;
        $new->SetVendutoResult = $SetVendutoResult;

        return $new;
    }
}
