<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetVersioneIngressiResponse implements ResultInterface
{
    private ?string $getVersioneIngressiResult = null;

    public function getGetVersioneIngressiResult(): ?string
    {
        return $this->getVersioneIngressiResult;
    }

    public function withGetVersioneIngressiResult(?string $getVersioneIngressiResult): static
    {
        $new = clone $this;
        $new->getVersioneIngressiResult = $getVersioneIngressiResult;

        return $new;
    }
}
