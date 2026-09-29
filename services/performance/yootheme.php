<?php

/**
 * The `source.init` hookup for PerformanceSources — required only behind
 * `function_exists('YOOtheme\app')` (PerformanceService::init()), so this
 * file may assume the parent theme is here.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace Atelier\Performance;

defined('ABSPATH') || exit;

/**
 * The one place a `$config` reaches YOOtheme — a parameter, not a global, so
 * the site's own labels (never atelier's own guess) end up on the query.
 *
 * @param array<string, mixed> $config The resolved performance service config.
 */
function register_calendar_source(array $config): void
{
    \YOOtheme\Event::on('source.init', static function ($source) use ($config): void {
        $source->queryType(['fields' => PerformanceSources::queries($config)]);
        // objectType() under a name the module already uses MERGES into it
        // (yootheme.md § the escape hatch) — Production's own fields sit
        // beside these, not on an orphaned type.
        $source->objectType('Production', ['fields' => PerformanceSources::productionFields($config)]);
    }, -10);
}
