<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/packages'])
    ->withPhpSets(php84: true)
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/packages/*/vendor',
    ]);
