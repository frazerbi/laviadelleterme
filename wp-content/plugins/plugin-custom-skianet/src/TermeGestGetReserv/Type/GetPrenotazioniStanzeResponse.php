<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetPrenotazioniStanzeResponse implements ResultInterface
{
    private ?GetPrenotazioniStanzeResult $getPrenotazioniStanzeResult = null;

    public function getGetPrenotazioniStanzeResult(): ?GetPrenotazioniStanzeResult
    {
        return $this->getPrenotazioniStanzeResult;
    }

    public function withGetPrenotazioniStanzeResult(?GetPrenotazioniStanzeResult $getPrenotazioniStanzeResult): static
    {
        $new = clone $this;
        $new->getPrenotazioniStanzeResult = $getPrenotazioniStanzeResult;

        return $new;
    }
}
