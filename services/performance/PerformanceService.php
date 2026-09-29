<?php

declare(strict_types=1);

namespace Atelier\Performance;

use Atelier\Production\ProductionModel;
use DateTimeImmutable;
use DateTimeZone;
use WP_Post;

defined('ABSPATH') || exit;

final class PerformanceService
{
    private const DEFAULTS = [
        'slug'     => 'performances',
        'archive'  => false,
        'calendar' => 'calendar',
        'labels'   => ['name' => 'Performances', 'singular_name' => 'Performance'],
    ];

    /** The calendar page's JSON-LD cap. */
    private const SCHEMA_LIMIT = 50;

    /** PerformanceModel::TICKET_STATUSES, minus `announced` (no offer at all). */
    private const AVAILABILITY = [
        'on_sale'   => 'InStock',
        'few_left'  => 'LimitedAvailability',
        'sold_out'  => 'SoldOut',
        'cancelled' => 'Discontinued',
    ];

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(private readonly PerformanceRepository $repository)
    {
        $this->init();
    }

    private function init(): void
    {
        $this->config = apply_filters('ntdst/service/performance/config', self::DEFAULTS);

        PerformanceModel::register($this->config);

        \NTDST_Template_Loader::addPath(__DIR__ . '/templates');
        add_shortcode('atelier_calendar_filters', [$this, 'renderFilters']);

        add_action('init', function (): void {
            ntdst_pages()->path(
                $this->calendarPaths('.ics'),
                fn () => ($body = $this->feed($_GET)) === false
                    ? false
                    : ntdst_response()->inline($body, $this->feedFilename()),
            );
        });

        add_action('after_setup_theme', function (): void {
            if (!function_exists('YOOtheme\app')) {
                return;
            }

            require_once __DIR__ . '/yootheme.php';
            register_calendar_source($this->config);
        }, 30);

        add_filter('ntdst/baseline/schema/custom', [$this, 'schema'], 10, 2);
    }

    /**
     * The one place the `.ics` URL is built (F2).
     *
     * @param array<string, string> $params
     */
    public function feedUrl(array $params): string
    {
        return add_query_arg(array_map('rawurlencode', $params), ntdst_pages()->url($this->calendarPaths('.ics/')));
    }

    private function feedFilename(): string
    {
        return $this->calendarWord() . '.ics';
    }

    /**
     * `calendar` is one word, or one per language; the first entry answers a language it lacks.
     */
    private function calendarWord(): string
    {
        $calendar = $this->config['calendar'] ?? self::DEFAULTS['calendar'];

        if (!is_array($calendar)) {
            return (string) $calendar;
        }

        return (string) ($calendar[ntdst_pages()->language()] ?? reset($calendar));
    }

    /**
     * @return string|array<string, string>
     */
    private function calendarPaths(string $tail): string|array
    {
        $calendar = $this->config['calendar'] ?? self::DEFAULTS['calendar'];

        return is_array($calendar)
            ? array_map(static fn ($word): string => '/' . $word . $tail, $calendar)
            : '/' . $calendar . $tail;
    }

    /**
     * `[atelier_calendar_filters]`. `$_GET` is read once, through `CalendarFilter::fromQuery()` (M1);
     * the attributes are editor text, escaped in the template (M4).
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

        $filter = CalendarFilter::fromQuery($_GET);
        $selected = $filter->toQuery();

        $feedParams = $selected;
        unset($feedParams['month'], $feedParams['past']);

        $toggled = $selected;
        unset($toggled['month']);
        if ($filter->past) {
            unset($toggled['past']);
        } else {
            $toggled['past'] = '1';
        }

        $permalink = (string) get_permalink();

        return ntdst_response()->html('calendar-filters', [
            'atts'         => $atts,
            'filter'       => $filter,
            'selected'     => $selected,
            'options'      => $this->repository->filterOptions($filter),
            'action'       => $permalink,
            'feed_url'     => $this->feedUrl($feedParams),
            'toggle_url'   => add_query_arg(array_map('rawurlencode', $toggled), $permalink),
            'toggle_label' => $filter->past ? $atts['upcoming'] : $atts['past'],
            'is_empty'     => $this->repository->calendar($filter) === [],
        ]);
    }

    /**
     * `TheaterEvent` JSON-LD, public audience only (R-c): the calendar page's current filter, or a
     * production's upcoming dates.
     *
     * @param array<int, mixed> $schemas
     *
     * @return array<int, mixed>
     */
    public function schema(array $schemas, ?WP_Post $post): array
    {
        if (is_page($this->calendarWord())) {
            $views = array_slice($this->publicViews(CalendarFilter::fromRequest()), 0, self::SCHEMA_LIMIT);
        } elseif ($post instanceof WP_Post && is_singular(ProductionModel::POST_TYPE)) {
            $views = $this->publicViews(CalendarFilter::forProduction($post->ID));
        } else {
            return $schemas;
        }

        foreach ($views as $view) {
            $schemas[] = $this->toTheaterEvent($view);
        }

        return $schemas;
    }

    /**
     * Public audience whatever the visitor filtered on: `?audience=school` must not reach the JSON-LD.
     *
     * @return list<array<string, mixed>>
     */
    private function publicViews(CalendarFilter $filter): array
    {
        $views = $this->repository->calendar($filter);

        return array_values(array_filter(
            $views,
            static fn(array $view): bool => $view['audience'] === 'public',
        ));
    }

    /**
     * @param array<string, mixed> $view
     *
     * @return array<string, mixed>
     */
    private function toTheaterEvent(array $view): array
    {
        /** @var WP_Post $production */
        $production = $view['production'];
        /** @var WP_Post $venue */
        $venue = $view['venue'];

        $allDay = (bool) $view['all_day'];
        /** @var DateTimeImmutable $start */
        $start = $view['start'];
        $runtime = self::runtimeMinutes($production);

        // An unset float field reads back as 0, never a real coordinate.
        $lat = $venue->fields['lat'] ?? null;
        $lng = $venue->fields['lng'] ?? null;
        $hasGeo = !empty($lat) && !empty($lng);

        $event = [
            '@context'            => 'https://schema.org',
            '@type'               => 'TheaterEvent',
            'name'                => (string) $production->post_title,
            'startDate'           => $allDay ? $start->format('Y-m-d') : $start->format(DATE_ATOM),
            'endDate'             => (!$allDay && $runtime !== null) ? $start->modify("+{$runtime} minutes")->format(DATE_ATOM) : null,
            'eventStatus'         => self::cancelled($view) ? 'EventCancelled' : 'EventScheduled',
            'eventAttendanceMode' => 'OfflineEventAttendanceMode',
            'location'            => array_filter([
                '@type'   => 'Place',
                'name'    => (string) $venue->post_title,
                'address' => array_filter([
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => (string) ($venue->fields['address'] ?? ''),
                    'addressLocality' => (string) ($venue->fields['city'] ?? ''),
                    'addressCountry'  => (string) ($venue->fields['country'] ?? ''),
                ], static fn(string $v): bool => $v !== ''),
                'geo'     => $hasGeo ? [
                    '@type'     => 'GeoCoordinates',
                    'latitude'  => (float) $lat,
                    'longitude' => (float) $lng,
                ] : null,
            ], static fn($v): bool => $v !== null && $v !== []),
            'workPerformed' => [
                '@type' => 'CreativeWork',
                'name'  => (string) $production->post_title,
                'url'   => (string) get_permalink($production),
            ],
            'url' => (string) get_permalink($production),
        ];

        // An unknown or unset status is no sale state, like 'announced' — never an empty availability.
        $ticketUrl = (string) $view['ticket_url'];
        $availability = self::AVAILABILITY[$view['ticket_status']] ?? null;
        if ($ticketUrl !== '' && $availability !== null) {
            $event['offers'] = [
                '@type'        => 'Offer',
                'url'          => $ticketUrl,
                'availability' => $availability,
            ];
        }

        return array_filter($event, static fn($v): bool => $v !== null);
    }

    /**
     * The calendar as one `.ics` window: `month`/`past` are dropped, so a forged `?past=1` cannot
     * narrow what a subscriber already cached. `false` (404, M3) when a filter was given as a
     * non-empty scalar but the allow-list dropped it — the feed refuses rather than widens.
     *
     * @param array<string, mixed> $query
     */
    public function feed(array $query): string|false
    {
        unset($query['month'], $query['past']);
        $filter = CalendarFilter::fromQuery($query);

        $kept = ['show' => $filter->production, 'audience' => $filter->audience, 'country' => $filter->country];
        foreach ($kept as $param => $value) {
            $given = $query[$param] ?? null;
            if (is_scalar($given) && (string) wp_unslash($given) !== '' && $value === null) {
                return false;
            }
        }

        $today = new DateTimeImmutable((string) current_time('Y-m-d'), wp_timezone());
        $from = $today->modify('-365 days')->format('Y-m-d');

        $events = array_map(
            [$this, 'toEvent'],
            $this->repository->calendar($filter, $from),
        );

        $name = (string) ($this->config['labels']['name'] ?? self::DEFAULTS['labels']['name']);

        return Ics::calendar($events, $name);
    }

    /**
     * `post_modified_gmt` is not a view key (M6), so the performance is re-read for DTSTAMP alone.
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

        $performance = $this->repository->find((int) $view['id']);
        $stamp = is_wp_error($performance)
            ? new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : new DateTimeImmutable($performance->post_modified_gmt, new DateTimeZone('UTC'));

        $location = implode(', ', array_filter([
            (string) $venue->post_title,
            (string) ($venue->fields['address'] ?? ''),
            (string) ($venue->fields['city'] ?? ''),
            (string) ($venue->fields['country'] ?? ''),
        ], static fn(string $part): bool => $part !== ''));

        return [
            'uid'       => 'performance-' . $view['id'] . '@' . self::homeHost(),
            'start'     => $view['start'],
            'all_day'   => (bool) $view['all_day'],
            'minutes'   => self::runtimeMinutes($production),
            'summary'   => (string) $production->post_title,
            'location'  => $location,
            'url'       => (string) get_permalink($production),
            'cancelled' => self::cancelled($view),
            'stamp'     => $stamp,
        ];
    }

    private static function homeHost(): string
    {
        return (string) wp_parse_url(home_url(), PHP_URL_HOST);
    }

    private static function runtimeMinutes(WP_Post $production): ?int
    {
        $minutes = (int) ($production->fields['runtime'] ?? 0);

        return $minutes > 0 ? $minutes : null;
    }

    /**
     * @param array<string, mixed> $view
     */
    private static function cancelled(array $view): bool
    {
        return $view['ticket_status'] === 'cancelled';
    }
}
