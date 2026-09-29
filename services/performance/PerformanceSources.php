<?php

declare(strict_types=1);

namespace Atelier\Performance;

use WP_Post;

defined('ABSPATH') || exit;

/**
 * The escape-hatch query the built-in `customPerformance` list cannot
 * express (an upcoming/past split with a from-date override), plus the two
 * fields merged onto YOOtheme's own `Production` type
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

    /**
     * Fields merged onto YOOtheme's own `Production` type (yootheme.md §
     * "the escape hatch": `objectType()` under a name the module already
     * uses MERGES into it, never replaces it). Loaded always — the
     * integration tier (no YOOtheme) calls `resolve_upcoming()` and
     * `resolve_calendar_feed_url()` directly.
     *
     * @param array<string, mixed> $config The resolved performance service config.
     * @return array<string, array<string, mixed>>
     */
    public static function productionFields(array $config): array
    {
        $label = (string) ($config['labels']['name'] ?? 'Performances');

        return [
            'upcomingPerformances' => [
                'type'       => ['listOf' => 'Performance'],
                'metadata'   => ['label' => 'Upcoming performances', 'group' => $label],
                'extensions' => ['call' => ['func' => __NAMESPACE__ . '\\resolve_upcoming']],
            ],
            'calendarFeedUrl' => [
                'type'       => 'String',
                'metadata'   => ['label' => 'Calendar feed URL', 'group' => $label],
                'extensions' => ['call' => ['func' => __NAMESPACE__ . '\\resolve_calendar_feed_url']],
            ],
        ];
    }
}

/**
 * Upcoming or past performances, filtered by the page's own query string
 * (`CalendarFilter::fromRequest()`) — builder arguments are not a filter
 * source. Returns `WP_Post`s — the shape every builder list wants — mapped
 * from `PerformanceRepository::calendar()`'s views.
 *
 * @return list<WP_Post>
 */
function resolve_calendar(mixed $root): array
{
    $filter = CalendarFilter::fromRequest();
    $views = ntdst_get(PerformanceRepository::class)->calendar($filter);

    return views_to_posts($views);
}

/**
 * One production's own upcoming dates — the same `calendar()` read
 * `resolve_calendar()` uses, narrowed to this production instead of the
 * page's query string (`CalendarFilter::forProduction()`).
 *
 * @return list<WP_Post>
 */
function resolve_upcoming(mixed $root): array
{
    $views = ntdst_get(PerformanceRepository::class)->calendar(CalendarFilter::forProduction((int) $root->ID));

    return views_to_posts($views);
}

/**
 * The calendar's own `.ics` feed, narrowed to this production — F2:
 * `PerformanceService::feedUrl()` is the one place this URL is built; the
 * filter form (Task 2) builds off the same method.
 */
function resolve_calendar_feed_url(mixed $root): string
{
    return ntdst_get(PerformanceService::class)->feedUrl(['show' => (string) $root->post_name]);
}

/**
 * @param list<array<string, mixed>> $views
 * @return list<WP_Post>
 */
function views_to_posts(array $views): array
{
    return array_values(array_filter(array_map(
        static fn(array $view): ?WP_Post => get_post((int) $view['id']),
        $views,
    )));
}
