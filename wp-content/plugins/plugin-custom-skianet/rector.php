<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodingStyle\Rector\PostInc\PostIncDecToPreIncDecRector;
use Rector\CodingStyle\Rector\String_\SymplifyQuoteEscapeRector;
use Rector\Config\RectorConfig;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\Strict\Rector\Ternary\DisallowedShortTernaryRuleFixerRector;
use Rector\Transform\Rector\StaticCall\StaticCallToMethodCallRector;
use Rector\ValueObject\PhpVersion;
use Utils\Rector\Rector\ConvertClassMethodsParametersPhpDocsToTypeHints;
use Utils\Rector\Rector\ConvertClassMethodsRemoveUselessPhpDocsFromTypeHints;
use Utils\Rector\Rector\ConvertClassMethodsReturnsPhpDocsToTypeHints;
use Utils\Rector\Rector\ConvertClassPropertiesPhpDocsToTypeHints;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/components',
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/utils',
        __DIR__.'/plugin-custom-skianet.php',
        __DIR__.'/rector.php',
    ])
    ->withSkip([
        '*/assets/*',
        '*/languages/*',
        '*/src/*',
        '*/vendor/*',
        DisallowedEmptyRuleFixerRector::class,
        DisallowedShortTernaryRuleFixerRector::class,
        PostIncDecToPreIncDecRector::class,
        RenamePropertyToMatchTypeRector::class,
        SimplifyEmptyCheckOnEmptyArrayRector::class,
        StaticCallToMethodCallRector::class,
        SymplifyQuoteEscapeRector::class,
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withImportNames(true, true, true, true)
    ->withPhpSets(false, false)
    ->withPreparedSets(true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true)
    ->withAttributesSets(true, true, true, true, true, true, true, true, true)
    ->withRules([
        ConvertClassMethodsParametersPhpDocsToTypeHints::class,
        ConvertClassMethodsRemoveUselessPhpDocsFromTypeHints::class,
        ConvertClassMethodsReturnsPhpDocsToTypeHints::class,
        ConvertClassPropertiesPhpDocsToTypeHints::class,
    ]);
