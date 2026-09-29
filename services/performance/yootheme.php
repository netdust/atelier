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

\YOOtheme\Event::on('source.init', static function ($source): void {
    $source->queryType(['fields' => PerformanceSources::queries()]);
}, -10);
