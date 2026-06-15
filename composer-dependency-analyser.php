<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

// symfony/uid and ramsey/uuid are optional: the client references them in src to
// generate a UUID when one is available, but a consumer only needs to install one
// of them (or supply their own id), so they stay as dev dependencies.
return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    ->ignoreErrorsOnPackage('symfony/uid', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('ramsey/uuid', [ErrorType::DEV_DEPENDENCY_IN_PROD])
;
