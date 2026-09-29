<?php

declare(strict_types=1);

namespace Atelier\Performance;

defined('ABSPATH') || exit;

final class PerformanceService
{
    private const DEFAULTS = [
        'slug'     => 'performances',
        'archive'  => false,
        // The URL segment Task 2's ICS feed reads (F2); unused until then.
        'calendar' => 'calendar',
        'labels'   => ['name' => 'Performances', 'singular_name' => 'Performance'],
    ];

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        $config = apply_filters('ntdst/service/performance/config', self::DEFAULTS);

        PerformanceModel::register($config);

        add_action('after_setup_theme', static function () use ($config): void {
            if (!function_exists('YOOtheme\app')) {
                return;
            }

            require_once __DIR__ . '/yootheme.php';
            register_calendar_source($config);
        }, 30);
    }
}
