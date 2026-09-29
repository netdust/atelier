<?php

declare(strict_types=1);

namespace Atelier\Performance;

use DateTimeImmutable;
use DateTimeZone;
use WP_Post;

defined('ABSPATH') || exit;

final class PerformanceService
{
    private const DEFAULTS = [
        'slug'     => 'performances',
        'archive'  => false,
        // The URL segment the .ics feed (Task 4) and feedUrl() read.
        'calendar' => 'calendar',
        'labels'   => ['name' => 'Performances', 'singular_name' => 'Performance'],
    ];

    /** @var array<string, mixed> */
    private array $config;

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        $this->config = apply_filters('ntdst/service/performance/config', self::DEFAULTS);

        PerformanceModel::register($this->config);

        \NTDST_Template_Loader::addPath(__DIR__ . '/templates');
        add_shortcode('atelier_calendar_filters', [$this, 'renderFilters']);

        // Pages::path() must run while WordPress is still building its rule
        // set (its own docblock); `init` is also where every other route on
        // the fleet registers, so this one does the same rather than riding
        // in early on the service's own construction.
        add_action('init', function (): void {
            $config = $this->config;

            ntdst_pages()->path(
                '/' . $config['calendar'] . '.ics',
                fn () => ($body = $this->feed($_GET)) === false
                    ? false
                    : ntdst_response()->inline($body, $config['calendar'] . '.ics'),
            );
        });

        add_action('after_setup_theme', function (): void {
            if (!function_exists('YOOtheme\app')) {
                return;
            }

            require_once __DIR__ . '/yootheme.php';
            register_calendar_source($this->config);
        }, 30);
    }

    /**
     * `add_query_arg` over the calendar's own `.ics` URL (F2) — the form
     * (below) and Task 4's feed link both build off this, so the segment is
     * named in exactly one place.
     *
     * @param array<string, string> $params
     */
    public function feedUrl(array $params): string
    {
        $calendar = (string) ($this->config['calendar'] ?? self::DEFAULTS['calendar']);

        return add_query_arg($params, ntdst_pages()->url('/' . $calendar . '.ics/'));
    }

    /**
     * The `[atelier_calendar_filters]` shortcode: a GET form to the current
     * page, one `<select>` per allow-listed filter, the past/upcoming
     * toggle, the empty message and the feed link. `$_GET` is read exactly
     * once, through `CalendarFilter::fromQuery()` (Threat model M1); every
     * attribute is editor-supplied free text, sanitized by `shortcode_atts()`
     * against these defaults and escaped in `filters.php` (M4).
     *
     * @param array<string, mixed>|string $atts
     */
    public function renderFilters($atts): string
    {
        $atts = shortcode_atts([
            'show_label'     => 'Production',
            'audience_label' => 'Audience',
            'country_label'  => 'Country',
            'month_label'    => 'Month',
            'all'            => 'All',
            'submit'         => 'Show',
            'past'           => 'Past dates',
            'upcoming'       => 'Upcoming dates',
            'empty'          => 'No performances for this selection.',
            'feed'           => 'Subscribe to your calendar',
        ], is_array($atts) ? $atts : [], 'atelier_calendar_filters');

        $repository = ntdst_get(PerformanceRepository::class);
        $filter = CalendarFilter::fromQuery($_GET);
        $selected = $filter->toQuery();

        $feedParams = $selected;
        unset($feedParams['month'], $feedParams['past']);

        $toggled = $selected;
        if ($filter->past) {
            unset($toggled['past']);
        } else {
            $toggled['past'] = '1';
        }

        $permalink = (string) get_permalink();

        return ntdst_response()->html('filters', [
            'atts'         => $atts,
            'filter'       => $filter,
            'selected'     => $selected,
            'options'      => $repository->filterOptions($filter),
            'action'       => $permalink,
            'feed_url'     => $this->feedUrl($feedParams),
            'toggle_url'   => add_query_arg($toggled, $permalink),
            'toggle_label' => $filter->past ? $atts['upcoming'] : $atts['past'],
            'is_empty'     => $repository->calendar($filter) === [],
        ]);
    }

    /**
     * The whole calendar as an `.ics` feed, or one production's with
     * `?show=<slug>`. `month` and `past` are dropped before the query ever
     * reaches `CalendarFilter::fromQuery()` (M1) — the feed is one window,
     * never a page of it — so a forged `?past=1` cannot narrow what a
     * subscriber's calendar app already cached.
     *
     * `false` (→ 404, Threat model M3) when the raw query named a `show` that
     * didn't resolve: an empty string or a non-scalar (`show[]=`) is treated
     * as no `show` at all, exactly like `CalendarFilter::fromQuery()` treats
     * it — only a slug that was GIVEN and did not resolve is a refusal, never
     * "no slug given".
     *
     * @param array<string, mixed> $query
     */
    public function feed(array $query): string|false
    {
        $show = $query['show'] ?? null;
        $showGiven = is_scalar($show) && (string) wp_unslash($show) !== '';

        unset($query['month'], $query['past']);
        $filter = CalendarFilter::fromQuery($query);

        if ($showGiven && $filter->production === null) {
            return false;
        }

        $repository = ntdst_get(PerformanceRepository::class);
        $today = new DateTimeImmutable((string) current_time('Y-m-d'), wp_timezone());
        $from = $today->modify('-365 days')->format('Y-m-d');

        $events = array_map(
            [$this, 'toEvent'],
            $repository->calendar($filter, $from),
        );

        $name = (string) ($this->config['labels']['name'] ?? self::DEFAULTS['labels']['name']);

        return Ics::calendar($events, $name);
    }

    /**
     * One `PerformanceView::from()` shape (id/start/all_day/production/venue/
     * ticket_status — `PerformanceView::KEYS`) as `Ics::calendar()`'s event
     * shape. `post_modified_gmt` is not one of `PerformanceView::KEYS` (M6),
     * so the performance is re-read here for it alone.
     *
     * @param array<string, mixed> $view
     *
     * @return array{uid:string, start:DateTimeImmutable, all_day:bool, minutes:?int, summary:string, location:string, url:string, cancelled:bool, stamp:DateTimeImmutable}
     */
    private function toEvent(array $view): array
    {
        /** @var WP_Post $production */
        $production = $view['production'];
        /** @var WP_Post $venue */
        $venue = $view['venue'];

        $performance = ntdst_get(PerformanceRepository::class)->find((int) $view['id']);
        $stamp = is_wp_error($performance)
            ? new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : new DateTimeImmutable($performance->post_modified_gmt, new DateTimeZone('UTC'));

        $location = implode(', ', array_filter([
            (string) $venue->post_title,
            (string) ($venue->fields['address'] ?? ''),
            (string) ($venue->fields['city'] ?? ''),
            (string) ($venue->fields['country'] ?? ''),
        ], static fn(string $part): bool => $part !== ''));

        $runtime = (int) ($production->fields['runtime'] ?? 0);

        return [
            'uid'       => 'performance-' . $view['id'] . '@' . self::homeHost(),
            'start'     => $view['start'],
            'all_day'   => (bool) $view['all_day'],
            'minutes'   => $runtime > 0 ? $runtime : null,
            'summary'   => (string) $production->post_title,
            'location'  => $location,
            'url'       => (string) get_permalink($production),
            'cancelled' => $view['ticket_status'] === 'cancelled',
            'stamp'     => $stamp,
        ];
    }

    private static function homeHost(): string
    {
        return (string) wp_parse_url(home_url(), PHP_URL_HOST);
    }
}
