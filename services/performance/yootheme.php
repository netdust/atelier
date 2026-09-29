<?php

/**
 * Required only once YOOtheme is known to be loaded (PerformanceService::init()).
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace Atelier\Performance;

defined('ABSPATH') || exit;

/**
 * @param array<string, mixed> $config The resolved performance service config.
 */
function register_calendar_source(array $config): void
{
    \YOOtheme\Event::on('source.init', static function ($source) use ($config): void {
        $source->queryType(['fields' => PerformanceSources::queries($config)]);
        // objectType() under an existing name merges into it rather than replacing it.
        $source->objectType('Production', ['fields' => PerformanceSources::productionFields($config)]);
    }, -10);
}
