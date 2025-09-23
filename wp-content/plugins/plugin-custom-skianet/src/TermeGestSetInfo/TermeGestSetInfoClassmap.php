<?php

declare(strict_types=1);

namespace TermeGestSetInfo;

use Soap\ExtSoapEngine\Configuration\ClassMap\ClassMap;
use Soap\ExtSoapEngine\Configuration\ClassMap\ClassMapCollection;

class TermeGestSetInfoClassmap
{
    public static function getCollection(): ClassMapCollection
    {
        return new ClassMapCollection(
            new ClassMap('ArrayOfString', Type\ArrayOfString::class),
            new ClassMap('GetInfoVenduto', Type\GetInfoVenduto::class),
            new ClassMap('GetInfoVendutoResponse', Type\GetInfoVendutoResponse::class),
            new ClassMap('GetInfoCodici', Type\GetInfoCodici::class),
            new ClassMap('GetInfoCodiciResponse', Type\GetInfoCodiciResponse::class),
            new ClassMap('SetVenduto', Type\SetVenduto::class),
            new ClassMap('SetVendutoResponse', Type\SetVendutoResponse::class),
        );
    }
}
