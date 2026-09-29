<?php

declare(strict_types=1);

namespace Atelier\Performance;

use WP_Post;

defined('ABSPATH') || exit;

/**
 * The one escape-hatch query the built-in `customPerformance` list cannot
 * express: an upcoming/past split with a from-date override
 * (ntdst-yootheme's yootheme.md § escape hatch). Loaded always — the
 * integration tier (twentytwentyfive, no YOOtheme) exercises it directly;
 * only yootheme.php touches the parent theme.
 */
final class PerformanceSources
{
    /** @return array<string, array<string, mixed>> */
    public static function queries(): array
    {
        return [
            'calendarPerformances' => [
                'type'     => ['listOf' => 'Performance'],
                'metadata' => [
                    'label'  => 'Speeldata',
                    'group'  => 'Speellijst',
                    'fields' => [
                        'past' => ['label' => 'Past', 'type' => 'boolean', 'default' => false],
                    ],
                ],
                'extensions' => ['call' => ['func' => __NAMESPACE__ . '\\resolve_calendar']],
            ],
        ];
    }
}

/**
 * Upcoming or past performances, as `CalendarFilter::fromRequest()` reads the
 * query's own `past` argument. Returns `WP_Post`s — the shape every builder
 * list wants — mapped from `PerformanceRepository::calendar()`'s views.
 *
 * @param array<string, mixed> $args
 * @return list<WP_Post>
 */
function resolve_calendar(mixed $root, array $args = []): array
{
    $filter = CalendarFilter::fromRequest($args);
    $views = ntdst_get(PerformanceRepository::class)->calendar($filter);

    return array_values(array_filter(array_map(
        static fn(array $view): ?WP_Post => get_post((int) $view['id']),
        $views,
    )));
}
