<?php

declare(strict_types=1);

namespace TermeGestGetReserv;

use Phpro\SoapClient\Caller\Caller;
use Phpro\SoapClient\Exception\SoapException;
use Phpro\SoapClient\Type\RequestInterface;
use Phpro\SoapClient\Type\ResultInterface;
use TermeGestGetReserv\Type\GetAnnulli;
use TermeGestGetReserv\Type\GetAnnulliResponse;
use TermeGestGetReserv\Type\GetCodiciBiglietti;
use TermeGestGetReserv\Type\GetCodiciBigliettiResponse;
use TermeGestGetReserv\Type\GetDisponibilita;
use TermeGestGetReserv\Type\GetDisponibilitaById;
use TermeGestGetReserv\Type\GetDisponibilitaByIdResponse;
use TermeGestGetReserv\Type\GetDisponibilitaResponse;
use TermeGestGetReserv\Type\GetFascia;
use TermeGestGetReserv\Type\GetFasciaResponse;
use TermeGestGetReserv\Type\GetImportReserv;
use TermeGestGetReserv\Type\GetImportReservResponse;
use TermeGestGetReserv\Type\GetPrenotazioniStanze;
use TermeGestGetReserv\Type\GetPrenotazioniStanzeResponse;
use TermeGestGetReserv\Type\GetRawDisponibilitaById;
use TermeGestGetReserv\Type\GetRawDisponibilitaByIdResponse;
use TermeGestGetReserv\Type\GetReservation;
use TermeGestGetReserv\Type\GetReservationResponse;
use TermeGestGetReserv\Type\GetStat;
use TermeGestGetReserv\Type\GetStatResponse;
use TermeGestGetReserv\Type\GetVersioneIngressi;
use TermeGestGetReserv\Type\GetVersioneIngressiResponse;
use TermeGestGetReserv\Type\SetCodiceBiglietto;
use TermeGestGetReserv\Type\SetCodiceBigliettoResponse;
use TermeGestGetReserv\Type\SetCodiceBuono;
use TermeGestGetReserv\Type\SetCodiceBuonoResponse;
use TermeGestGetReserv\Type\SetPrenotazione;
use TermeGestGetReserv\Type\SetPrenotazioneResponse;
use TermeGestGetReserv\Type\SetReserv;
use TermeGestGetReserv\Type\SetReservResponse;

use function Psl\Type\instance_of;

class TermeGestGetReservClient
{
    /**
     * @var Caller
     */
    private $caller;

    public function __construct(Caller $caller)
    {
        $this->caller = $caller;
    }

    /**
     * @param GetAnnulli&RequestInterface $parameters
     * @return GetAnnulliResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getAnnulli(GetAnnulli $parameters): GetAnnulliResponse
    {
        $response = ($this->caller)('getAnnulli', $parameters);

        instance_of(GetAnnulliResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param GetCodiciBiglietti&RequestInterface $parameters
     * @return GetCodiciBigliettiResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getCodiciBiglietti(GetCodiciBiglietti $parameters): GetCodiciBigliettiResponse
    {
        $response = ($this->caller)('getCodiciBiglietti', $parameters);

        instance_of(GetCodiciBigliettiResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param GetDisponibilita&RequestInterface $parameters
     * @return GetDisponibilitaResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getDisponibilita(GetDisponibilita $parameters): GetDisponibilitaResponse
    {
        $response = ($this->caller)('getDisponibilita', $parameters);

        instance_of(GetDisponibilitaResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param GetDisponibilitaById&RequestInterface $parameters
     * @return GetDisponibilitaByIdResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getDisponibilitaById(GetDisponibilitaById $parameters): GetDisponibilitaByIdResponse
    {
        $response = ($this->caller)('getDisponibilitaById', $parameters);

        instance_of(GetDisponibilitaByIdResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param GetFascia&RequestInterface $parameters
     * @return GetFasciaResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getFascia(GetFascia $parameters): GetFasciaResponse
    {
        $response = ($this->caller)('getFascia', $parameters);

        instance_of(GetFasciaResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param GetImportReserv&RequestInterface $parameters
     * @return GetImportReservResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getImportReserv(GetImportReserv $parameters): GetImportReservResponse
    {
        $response = ($this->caller)('getImportReserv', $parameters);

        instance_of(GetImportReservResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param GetPrenotazioniStanze&RequestInterface $parameters
     * @return GetPrenotazioniStanzeResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getPrenotazioniStanze(GetPrenotazioniStanze $parameters): GetPrenotazioniStanzeResponse
    {
        $response = ($this->caller)('getPrenotazioniStanze', $parameters);

        instance_of(GetPrenotazioniStanzeResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param GetRawDisponibilitaById&RequestInterface $parameters
     * @return GetRawDisponibilitaByIdResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getRawDisponibilitaById(GetRawDisponibilitaById $parameters): GetRawDisponibilitaByIdResponse
    {
        $response = ($this->caller)('getRawDisponibilitaById', $parameters);

        instance_of(GetRawDisponibilitaByIdResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param GetReservation&RequestInterface $parameters
     * @return GetReservationResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getReservation(GetReservation $parameters): GetReservationResponse
    {
        $response = ($this->caller)('getReservation', $parameters);

        instance_of(GetReservationResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param GetStat&RequestInterface $parameters
     * @return GetStatResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getStat(GetStat $parameters): GetStatResponse
    {
        $response = ($this->caller)('GetStat', $parameters);

        instance_of(GetStatResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param GetVersioneIngressi&RequestInterface $parameters
     * @return GetVersioneIngressiResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getVersioneIngressi(GetVersioneIngressi $parameters): GetVersioneIngressiResponse
    {
        $response = ($this->caller)('getVersioneIngressi', $parameters);

        instance_of(GetVersioneIngressiResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface&SetCodiceBiglietto $parameters
     * @return ResultInterface&SetCodiceBigliettoResponse
     *
     * @throws SoapException
     */
    public function setCodiceBiglietto(SetCodiceBiglietto $parameters): SetCodiceBigliettoResponse
    {
        $response = ($this->caller)('setCodiceBiglietto', $parameters);

        instance_of(SetCodiceBigliettoResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface&SetCodiceBuono $parameters
     * @return ResultInterface&SetCodiceBuonoResponse
     *
     * @throws SoapException
     */
    public function setCodiceBuono(SetCodiceBuono $parameters): SetCodiceBuonoResponse
    {
        $response = ($this->caller)('setCodiceBuono', $parameters);

        instance_of(SetCodiceBuonoResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface&SetPrenotazione $parameters
     * @return ResultInterface&SetPrenotazioneResponse
     *
     * @throws SoapException
     */
    public function setPrenotazione(SetPrenotazione $parameters): SetPrenotazioneResponse
    {
        $response = ($this->caller)('SetPrenotazione', $parameters);

        instance_of(SetPrenotazioneResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface&SetReserv $parameters
     * @return ResultInterface&SetReservResponse
     *
     * @throws SoapException
     */
    public function setReserv(SetReserv $parameters): SetReservResponse
    {
        $response = ($this->caller)('setReserv', $parameters);

        instance_of(SetReservResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }
}
