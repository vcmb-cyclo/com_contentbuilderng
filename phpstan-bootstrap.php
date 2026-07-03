<?php

/**
 * PHPStan bootstrap: defines the Joomla path constants for static analysis.
 * Values are irrelevant; only the symbols must exist.
 */

\defined('_JEXEC') || \define('_JEXEC', 1);

foreach (
    [
        'JPATH_ROOT',
        'JPATH_SITE',
        'JPATH_BASE',
        'JPATH_ADMINISTRATOR',
        'JPATH_API',
        'JPATH_CLI',
        'JPATH_CONFIGURATION',
        'JPATH_CACHE',
        'JPATH_INSTALLATION',
        'JPATH_LIBRARIES',
        'JPATH_MANIFESTS',
        'JPATH_PLUGINS',
        'JPATH_PUBLIC',
        'JPATH_THEMES',
        'JPATH_COMPONENT',
        'JPATH_COMPONENT_SITE',
        'JPATH_COMPONENT_ADMINISTRATOR',
    ] as $cbPhpstanConstant
) {
    \defined($cbPhpstanConstant) || \define($cbPhpstanConstant, __DIR__);
}

\defined('JDEBUG') || \define('JDEBUG', false);
