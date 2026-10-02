<?php

declare(strict_types=1);

namespace Atelier\Planning;

use Atelier\Models\PersonRepository;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * The titles assignments and absences carry: composed from their fields,
 * never typed. `sync()` applies them on both write paths — the repository
 * and the admin screen, which saves through core's update() — reading and
 * writing through the repositories only.
 */
final class PlanningTitles
{
    /** @var array<int, true> records being titled right now: the write fires `updated` again, which must not loop */
    private static array $inFlight = [];

    public function __construct(
        private readonly AssignmentRepository $assignments,
        private readonly AbsenceRepository $absences,
        private readonly ActivityRepository $activities,
        private readonly PersonRepository $persons,
    ) {
    }

    public static function assignment(string $who, string $activityTitle, string $date): string
    {
        return sprintf('%s — %s (%s)', $who, $activityTitle, $date);
    }

    public static function absence(string $who, string $from, string $to): string
    {
        return $from === $to
            ? sprintf('%s %s', $who, $from)
            : sprintf('%s %s – %s', $who, $from, $to);
    }

    public function sync(string $postType, int $id): void
    {
        if (isset(self::$inFlight[$id])) {
            return;
        }

        self::$inFlight[$id] = true;
        try {
            $this->apply($postType, $id);
        } finally {
            unset(self::$inFlight[$id]);
        }
    }

    private function apply(string $postType, int $id): void
    {
        $repository = $postType === AssignmentModel::POST_TYPE ? $this->assignments : $this->absences;
        $record = $repository->find($id, 'any');
        if (!$record instanceof WP_Post) {
            return;
        }

        $title = $postType === AssignmentModel::POST_TYPE ? $this->forAssignment($record) : $this->forAbsence($record);
        if ($title === null || $title === $record->post_title) {
            return;
        }

        $written = $repository->update($id, ['title' => $title]);
        if (is_wp_error($written)) {
            ntdst_log('atelier')->warning(sprintf(
                'planning: title of %s %d not written — %s',
                $postType,
                $id,
                $written->get_error_message(),
            ));
        }
    }

    private function forAssignment(WP_Post $assignment): ?string
    {
        $activity = $this->activities->find((int) ($assignment->fields['activity'][0] ?? 0), 'any');
        if (!$activity instanceof WP_Post) {
            return null;
        }

        return self::assignment(
            $this->who((int) ($assignment->fields['person'][0] ?? 0), (string) ($assignment->fields['name'] ?? '')),
            (string) $activity->post_title,
            (string) ($activity->fields['date'] ?? ''),
        );
    }

    private function forAbsence(WP_Post $absence): string
    {
        return self::absence(
            $this->who((int) ($absence->fields['person'][0] ?? 0), ''),
            (string) ($absence->fields['from'] ?? ''),
            (string) ($absence->fields['to'] ?? ''),
        );
    }

    private function who(int $personId, string $name): string
    {
        if ($personId === 0) {
            return $name;
        }

        $person = $this->persons->find($personId, 'any');

        return $person instanceof WP_Post ? (string) $person->post_title : $name;
    }
}
