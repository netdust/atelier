<?php

declare(strict_types=1);

namespace Atelier\Performance;

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
}
