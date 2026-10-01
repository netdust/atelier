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

    private const TITLED = [AssignmentModel::POST_TYPE, AbsenceModel::POST_TYPE];

    public function __construct(
        private readonly PlanningTitles $titles,
        private readonly ActivityColumns $columns,
    ) {
        $this->init();
    }

    private function init(): void
    {
        $config = apply_filters('ntdst/service/planning/config', self::DEFAULTS);

        ActivityModel::register($config['activity']);
        AssignmentModel::register($config['assignment']);
        AbsenceModel::register($config['absence']);

        $this->columns->register();

        // Both write paths end here: the repository, and the admin screen through core's update().
        foreach (['ntdst/model/created', 'ntdst/model/updated'] as $hook) {
            add_action($hook, function (string $postType, int $id): void {
                if (in_array($postType, self::TITLED, true)) {
                    $this->titles->sync($postType, $id);
                }
            }, 10, 2);
        }
    }
}
