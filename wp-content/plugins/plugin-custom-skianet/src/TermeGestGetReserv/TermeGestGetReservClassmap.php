<?php

declare(strict_types=1);

namespace TermeGestGetReserv;

use Soap\ExtSoapEngine\Configuration\ClassMap\ClassMap;
use Soap\ExtSoapEngine\Configuration\ClassMap\ClassMapCollection;

class TermeGestGetReservClassmap
{
    public static function getCollection(): ClassMapCollection
    {
        return new ClassMapCollection(
            new ClassMap('getReservation', Type\GetReservation::class),
            new ClassMap('getReservationResponse', Type\GetReservationResponse::class),
            new ClassMap('getReservationResult', Type\GetReservationResult::class),
            new ClassMap('getImportReserv', Type\GetImportReserv::class),
            new ClassMap('getImportReservResponse', Type\GetImportReservResponse::class),
            new ClassMap('getImportReservResult', Type\GetImportReservResult::class),
            new ClassMap('setReserv', Type\SetReserv::class),
            new ClassMap('codeandid', Type\Codeandid::class),
            new ClassMap('setReservResponse', Type\SetReservResponse::class),
            new ClassMap('getAnnulli', Type\GetAnnulli::class),
            new ClassMap('getAnnulliResponse', Type\GetAnnulliResponse::class),
            new ClassMap('getAnnulliResult', Type\GetAnnulliResult::class),
            new ClassMap('getCodiciBiglietti', Type\GetCodiciBiglietti::class),
            new ClassMap('getCodiciBigliettiResponse', Type\GetCodiciBigliettiResponse::class),
            new ClassMap('getCodiciBigliettiResult', Type\GetCodiciBigliettiResult::class),
            new ClassMap('setCodiceBiglietto', Type\SetCodiceBiglietto::class),
            new ClassMap('setCodiceBigliettoResponse', Type\SetCodiceBigliettoResponse::class),
            new ClassMap('getVersioneIngressi', Type\GetVersioneIngressi::class),
            new ClassMap('getVersioneIngressiResponse', Type\GetVersioneIngressiResponse::class),
            new ClassMap('setCodiceBuono', Type\SetCodiceBuono::class),
            new ClassMap('setCodiceBuonoResponse', Type\SetCodiceBuonoResponse::class),
            new ClassMap('getPrenotazioniStanze', Type\GetPrenotazioniStanze::class),
            new ClassMap('getPrenotazioniStanzeResponse', Type\GetPrenotazioniStanzeResponse::class),
            new ClassMap('getPrenotazioniStanzeResult', Type\GetPrenotazioniStanzeResult::class),
            new ClassMap('GetStat', Type\GetStat::class),
            new ClassMap('GetStatResponse', Type\GetStatResponse::class),
            new ClassMap('GetStatResult', Type\GetStatResult::class),
            new ClassMap('getFascia', Type\GetFascia::class),
            new ClassMap('getFasciaResponse', Type\GetFasciaResponse::class),
            new ClassMap('getFasciaResult', Type\GetFasciaResult::class),
            new ClassMap('getDisponibilita', Type\GetDisponibilita::class),
            new ClassMap('getDisponibilitaResponse', Type\GetDisponibilitaResponse::class),
            new ClassMap('getDisponibilitaResult', Type\GetDisponibilitaResult::class),
            new ClassMap('getDisponibilitaById', Type\GetDisponibilitaById::class),
            new ClassMap('getDisponibilitaByIdResponse', Type\GetDisponibilitaByIdResponse::class),
            new ClassMap('getRawDisponibilitaById', Type\GetRawDisponibilitaById::class),
            new ClassMap('getRawDisponibilitaByIdResponse', Type\GetRawDisponibilitaByIdResponse::class),
            new ClassMap('SetPrenotazione', Type\SetPrenotazione::class),
            new ClassMap('SetPrenotazioneResponse', Type\SetPrenotazioneResponse::class),
        );
    }
}
