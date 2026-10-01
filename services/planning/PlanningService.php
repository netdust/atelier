<?php

declare(strict_types=1);

namespace Atelier\Planning;

defined('ABSPATH') || exit;

/**
 * Planning: activities, the people on them, and who is away. Three private
 * records a site relabels through `ntdst/service/planning/config`.
 */
final class PlanningService
{
    private const DEFAULTS = [
        'activity'   => ['labels' => ['name' => 'Activities', 'singular_name' => 'Activity']],
        'assignment' => ['labels' => ['name' => 'Assignments', 'singular_name' => 'Assignment']],
        'absence'    => ['labels' => ['name' => 'Absences', 'singular_name' => 'Absence']],
    ];

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        $config = apply_filters('ntdst/service/planning/config', self::DEFAULTS);

        ActivityModel::register($config['activity']);
        AssignmentModel::register($config['assignment']);
        AbsenceModel::register($config['absence']);
    }
}
