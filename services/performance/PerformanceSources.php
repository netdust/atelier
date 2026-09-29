<?php

declare(strict_types=1);

namespace Atelier\Performance;

use WP_Post;

defined('ABSPATH') || exit;

/**
 * The calendar query the built-in `customPerformance` list cannot express, and the fields merged
 * onto YOOtheme's `Production` type. Loaded without YOOtheme so the integration tier can call it.
 */
final class PerformanceSources
{
    /**
     * Labelled with the site's own word for the model (`Speeldata`), never atelier's.
     *
     * @param array<string, mixed> $config The resolved performance service config.
     * @return array<string, array<string, mixed>>
     */
    public static function queries(array $config): array
    {
        $label = (string) ($config['labels']['name'] ?? 'Performances');

        return [
            'calendarPerformances' => [
                'type'     => ['listOf' => 'Performance'],
                'metadata' => ['label' => $label, 'group' => $label],
                'extensions' => ['call' => ['func' => __NAMESPACE__ . '\\resolve_calendar']],
            ],
        ];
    }

    /**
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
 * Filtered by the page's own query string; builder arguments are not a filter source.
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
 * @return list<WP_Post>
 */
function resolve_upcoming(mixed $root): array
{
    $views = ntdst_get(PerformanceRepository::class)->calendar(CalendarFilter::forProduction((int) $root->ID));

    return views_to_posts($views);
}

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
