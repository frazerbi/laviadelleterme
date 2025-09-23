<?php

declare(strict_types=1);

namespace TermeGestSetInfo;

use Phpro\SoapClient\Caller\EngineCaller;
use Phpro\SoapClient\Caller\EventDispatchingCaller;
use Phpro\SoapClient\Soap\DefaultEngineFactory;
use Soap\ExtSoapEngine\ExtSoapOptions;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TermeGestSetInfoClientFactory
{
    public static function factory(string $wsdl): TermeGestSetInfoClient
    {
        $engine = DefaultEngineFactory::create(
            ExtSoapOptions::defaults($wsdl, [])
                ->withClassMap(TermeGestSetInfoClassmap::getCollection())
        );

        $eventDispatcher = new EventDispatcher();
        $caller = new EventDispatchingCaller(new EngineCaller($engine), $eventDispatcher);

        return new TermeGestSetInfoClient($caller);
    }
}
