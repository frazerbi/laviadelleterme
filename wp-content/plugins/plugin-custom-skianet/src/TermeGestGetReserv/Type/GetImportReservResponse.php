<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetImportReservResponse implements ResultInterface
{
    private ?GetImportReservResult $getImportReservResult = null;

    public function getGetImportReservResult(): ?GetImportReservResult
    {
        return $this->getImportReservResult;
    }

    public function withGetImportReservResult(?GetImportReservResult $getImportReservResult): static
    {
        $new = clone $this;
        $new->getImportReservResult = $getImportReservResult;

        return $new;
    }
}
