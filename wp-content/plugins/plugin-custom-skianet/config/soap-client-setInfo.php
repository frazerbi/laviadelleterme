<?php

declare(strict_types=1);

use Phpro\SoapClient\CodeGenerator\Assembler\AbstractClassAssembler;
use Phpro\SoapClient\CodeGenerator\Assembler\ConstructorAssembler;
use Phpro\SoapClient\CodeGenerator\Assembler\ConstructorAssemblerOptions;
use Phpro\SoapClient\CodeGenerator\Assembler\ExtendingTypeAssembler;
use Phpro\SoapClient\CodeGenerator\Assembler\GetterAssembler;
use Phpro\SoapClient\CodeGenerator\Assembler\GetterAssemblerOptions;
use Phpro\SoapClient\CodeGenerator\Assembler\ImmutableSetterAssembler;
use Phpro\SoapClient\CodeGenerator\Assembler\ImmutableSetterAssemblerOptions;
use Phpro\SoapClient\CodeGenerator\Assembler\RequestAssembler;
use Phpro\SoapClient\CodeGenerator\Assembler\ResultAssembler;
use Phpro\SoapClient\CodeGenerator\Config\Config;
use Phpro\SoapClient\CodeGenerator\Rules\AssembleRule;
use Phpro\SoapClient\CodeGenerator\Rules\IsAbstractTypeRule;
use Phpro\SoapClient\CodeGenerator\Rules\IsExtendingTypeRule;
use Phpro\SoapClient\CodeGenerator\Rules\IsRequestRule;
use Phpro\SoapClient\CodeGenerator\Rules\IsResultRule;
use Phpro\SoapClient\CodeGenerator\Rules\MultiRule;
use Phpro\SoapClient\Soap\CodeGeneratorEngineFactory;

return Config::create()
    ->setEngine(
        $engine = CodeGeneratorEngineFactory::create(
            'https://www.termegest.it/setinfo.asmx?WSDL'
        )
    )
    ->setTypeDestination('src/TermeGestSetInfo/Type')
    ->setTypeNamespace('TermeGestSetInfo\Type')
    ->setClientDestination('src/TermeGestSetInfo')
    ->setClientName('TermeGestSetInfoClient')
    ->setClientNamespace('TermeGestSetInfo')
    ->setClassMapDestination('src/TermeGestSetInfo')
    ->setClassMapName('TermeGestSetInfoClassmap')
    ->setClassMapNamespace('TermeGestSetInfo')
    ->addRule(new AssembleRule(new GetterAssembler(new GetterAssemblerOptions())))
    ->addRule(
        new AssembleRule(
            new ImmutableSetterAssembler(
                new ImmutableSetterAssemblerOptions()
            )
        )
    )
    ->addRule(
        new IsRequestRule(
            $engine->getMetadata(),
            new MultiRule([
                new AssembleRule(new RequestAssembler()),
                new AssembleRule(new ConstructorAssembler(new ConstructorAssemblerOptions())),
            ])
        )
    )
    ->addRule(
        new IsResultRule(
            $engine->getMetadata(),
            new MultiRule([
                new AssembleRule(new ResultAssembler()),
            ])
        )
    )
    ->addRule(
        new IsExtendingTypeRule(
            $engine->getMetadata(),
            new AssembleRule(new ExtendingTypeAssembler())
        )
    )
    ->addRule(
        new IsAbstractTypeRule(
            $engine->getMetadata(),
            new AssembleRule(new AbstractClassAssembler())
        )
    );
