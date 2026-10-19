<?php

/**
 * The fixture site atelier's integration tests boot on: the services a site
 * lists, with no overrides, so each service runs on its own DEFAULTS. Read by
 * the test site's tests/bootstrap-site.php and handed to NTDST_Bootstrap.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    'services' => [
        'auto_discover' => false,

        'core' => [
            \Atelier\Production\ProductionService::class,
            \Atelier\Models\ModelsService::class,
            \Atelier\Performance\PerformanceService::class,
        ],
        'admin'       => [],
        'conditional' => [],
        'overrides'   => [],
    ],
];
