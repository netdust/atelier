<?php

declare(strict_types=1);

namespace Atelier\Planning;

defined('ABSPATH') || exit;

/**
 * The planning repositories are the views grid's writers: a write from the
 * grid meets the same rules as one from anywhere else.
 */
final class PlanningWriters
{
    public function __construct(
        private readonly ActivityRepository $activities,
        private readonly AssignmentRepository $assignments,
        private readonly AbsenceRepository $absences,
    ) {
    }

    public function register(): void
    {
        add_filter('ntdst/baseline/views/writers', [$this, 'writers']);
    }

    /**
     * A writer registered earlier for one of these types is kept; an earlier
     * callback that answered no map is not a reason to lose these three.
     *
     * @return array<string, object>
     */
    public function writers(mixed $writers): array
    {
        return (is_array($writers) ? $writers : []) + [
            ActivityModel::POST_TYPE   => $this->activities,
            AssignmentModel::POST_TYPE => $this->assignments,
            AbsenceModel::POST_TYPE    => $this->absences,
        ];
    }
}
