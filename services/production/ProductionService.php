<?php

declare(strict_types=1);

namespace Atelier\Production;

use NTDST_Service_Meta;

defined('ABSPATH') || exit;

final class ProductionService implements NTDST_Service_Meta
{
    private const DEFAULTS = [
        'slug'    => 'productions',
        'archive' => 'productions',
        'labels'  => ['name' => 'Productions', 'singular_name' => 'Production'],
    ];

    public function __construct()
    {
        $this->init();
    }

    public static function metadata(): array
    {
        return [
            'description' => 'Productions: the creative work a company tours, with credits, media and press.',
            'priority'    => 10,
        ];
    }

    private function init(): void
    {
        ProductionModel::register(apply_filters('ntdst/service/production/config', self::DEFAULTS));

        // The builder queries need YOOtheme; without the parent theme they stay inert.
        if (function_exists('YOOtheme\\app')) {
            require_once __DIR__ . '/yootheme.php';
        }
    }
}
