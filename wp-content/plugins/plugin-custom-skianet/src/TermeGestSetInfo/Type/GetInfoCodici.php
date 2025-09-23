<?php

declare(strict_types=1);

namespace TermeGestSetInfo\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetInfoCodici implements RequestInterface
{
    private ?ArrayOfString $listacodici = null;

    /**
     * Constructor
     */
    public function __construct(?ArrayOfString $listacodici)
    {
        $this->listacodici = $listacodici;
    }

    public function getListacodici(): ?ArrayOfString
    {
        return $this->listacodici;
    }

    public function withListacodici(?ArrayOfString $listacodici): static
    {
        $new = clone $this;
        $new->listacodici = $listacodici;

        return $new;
    }
}
