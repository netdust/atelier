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
    /**
     * The label a site gave the model (`ntdst/service/performance/config`),
     * never a word of atelier's own choosing — a site that renames
     * `Performances` to `Speeldata` sees `Speeldata` in the picker too.
     *
     * @param array<string, mixed> $config The resolved performance service
     *                                     config — same shape `PerformanceModel::register()` takes.
     * @return array<string, array<string, mixed>>
     */
    public static function queries(array $config): array
    {
        $label = (string) ($config['labels']['name'] ?? 'Performances');

        return [
            'calendarPerformances' => [
                'type'     => ['listOf' => 'Performance'],
                'metadata' => [
                    'label'  => $label,
                    'group'  => $label,
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
