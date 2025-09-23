<?php

declare(strict_types=1);

namespace TermeGestGetReserv;

use Phpro\SoapClient\Caller\EngineCaller;
use Phpro\SoapClient\Caller\EventDispatchingCaller;
use Phpro\SoapClient\Soap\DefaultEngineFactory;
use Soap\ExtSoapEngine\ExtSoapOptions;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TermeGestGetReservClientFactory
{
    public static function factory(string $wsdl): TermeGestGetReservClient
    {
        $engine = DefaultEngineFactory::create(
            ExtSoapOptions::defaults($wsdl, [])
                ->withClassMap(TermeGestGetReservClassmap::getCollection())
        );

        $eventDispatcher = new EventDispatcher();
        $caller = new EventDispatchingCaller(new EngineCaller($engine), $eventDispatcher);

        return new TermeGestGetReservClient($caller);
    }
}
