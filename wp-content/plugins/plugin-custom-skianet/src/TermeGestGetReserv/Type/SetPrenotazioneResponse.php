<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

use Phpro\SoapClient\Type\ResultInterface;

class SetPrenotazioneResponse implements ResultInterface
{
    private ?string $ErrMsg = null;

    private bool $SetPrenotazioneResult;

    public function getErrMsg(): ?string
    {
        return $this->ErrMsg;
    }

    public function getSetPrenotazioneResult(): bool
    {
        return $this->SetPrenotazioneResult;
    }

    public function withErrMsg(?string $ErrMsg): static
    {
        $new = clone $this;
        $new->ErrMsg = $ErrMsg;

        return $new;
    }

    public function withSetPrenotazioneResult(bool $SetPrenotazioneResult): static
    {
        $new = clone $this;
        $new->SetPrenotazioneResult = $SetPrenotazioneResult;

        return $new;
    }
}
