<?php

declare(strict_types=1);

namespace TermeGestSetInfo;

use Phpro\SoapClient\Caller\Caller;
use Phpro\SoapClient\Exception\SoapException;
use Phpro\SoapClient\Type\RequestInterface;
use Phpro\SoapClient\Type\ResultInterface;
use TermeGestSetInfo\Type\GetInfoCodici;
use TermeGestSetInfo\Type\GetInfoCodiciResponse;
use TermeGestSetInfo\Type\GetInfoVenduto;
use TermeGestSetInfo\Type\GetInfoVendutoResponse;
use TermeGestSetInfo\Type\SetVenduto;
use TermeGestSetInfo\Type\SetVendutoResponse;

use function Psl\Type\instance_of;

class TermeGestSetInfoClient
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
     * @param GetInfoCodici&RequestInterface $parameters
     * @return GetInfoCodiciResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getInfoCodici(GetInfoCodici $parameters): GetInfoCodiciResponse
    {
        $response = ($this->caller)('GetInfoCodici', $parameters);

        instance_of(GetInfoCodiciResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param GetInfoVenduto&RequestInterface $parameters
     * @return GetInfoVendutoResponse&ResultInterface
     *
     * @throws SoapException
     */
    public function getInfoVenduto(GetInfoVenduto $parameters): GetInfoVendutoResponse
    {
        $response = ($this->caller)('GetInfoVenduto', $parameters);

        instance_of(GetInfoVendutoResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface&SetVenduto $parameters
     * @return ResultInterface&SetVendutoResponse
     *
     * @throws SoapException
     */
    public function setVenduto(SetVenduto $parameters): SetVendutoResponse
    {
        $response = ($this->caller)('SetVenduto', $parameters);

        instance_of(SetVendutoResponse::class)->assert($response);
        instance_of(ResultInterface::class)->assert($response);

        return $response;
    }
}
