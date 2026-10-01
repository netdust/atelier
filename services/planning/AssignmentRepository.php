<?php

declare(strict_types=1);

namespace Atelier\Planning;

use Atelier\Models\PersonRepository;
use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * The one place `assignment` data is read or written. The rules core's
 * per-field validators cannot hold live here: one of person or name, one
 * assignment per person per activity, an activity that is one.
 */
final class AssignmentRepository
{
    public function __construct(
        private readonly PersonRepository $persons,
        private readonly ActivityRepository $activities,
    ) {
    }

    public function find(int $id, string $status = 'publish'): WP_Post|WP_Error
    {
        return ntdst_data()->get(AssignmentModel::POST_TYPE)->find($id, $status);
    }

    /** `activity` and `person` may be given as ints; `status` defaults to the first of the vocabulary. */
    public function create(array $data): WP_Post|WP_Error
    {
        $data = self::normalise($data);
        $data['status'] ??= AssignmentModel::STATUSES[0];

        $refused = $this->refuse($data, null);

        return $refused ?? ntdst_data()->get(AssignmentModel::POST_TYPE)->create($data);
    }

    public function update(int $id, array $data): WP_Post|WP_Error
    {
        $data = self::normalise($data);

        $refused = $this->refuse($data, $id);

        return $refused ?? ntdst_data()->get(AssignmentModel::POST_TYPE)->update($id, $data);
    }

    /** To the trash, never past it; an id of another type is core's `not_found`, as on update(). */
    public function delete(int $id): bool|WP_Error
    {
        return ntdst_data()->get(AssignmentModel::POST_TYPE)->delete($id);
    }

    /**
     * The assignments on one activity, oldest first.
     *
     * @return list<array<string, mixed>> query rows: post columns at the top level, the fields under `meta`
     */
    public function forActivity(int $activityId, ?string $status = null): array
    {
        $query = ntdst_data()->get(AssignmentModel::POST_TYPE)
            ->whereRelated('activity', $activityId)
            ->orderBy('ID', 'ASC')
            ->limit(-1)
            ->withMeta();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return array_values($query->get());
    }

    /**
     * A person's assignments with their activity attached, by the activity's date and start.
     *
     * @return list<array<string, mixed>> assignment rows, each with an `activity` row
     */
    public function forPerson(int $personId, ?string $from = null, ?string $to = null): array
    {
        $assignments = ntdst_data()->get(AssignmentModel::POST_TYPE)
            ->whereRelated('person', $personId)
            ->limit(-1)
            ->withMeta()
            ->get();

        $ids = array_values(array_filter(array_map(
            static fn(array $row): int => (int) ($row['meta']['activity'][0] ?? 0),
            $assignments,
        )));

        if ($ids === []) {
            return [];
        }

        $byActivity = [];
        foreach ($assignments as $assignment) {
            $byActivity[(int) ($assignment['meta']['activity'][0] ?? 0)] = $assignment;
        }

        // between() orders by date then start; a missing bound is the far past or future.
        $rows = [];
        foreach ($this->activities->between($from ?? '0000-01-01', $to ?? '9999-12-31', ['ids' => $ids]) as $activity) {
            $rows[] = $byActivity[(int) $activity['id']] + ['activity' => $activity];
        }

        return $rows;
    }

    /** Confirmed assignments are the covers; `requested` and `maybe` are not (R-4). */
    public function headcount(int $activityId): int
    {
        return count($this->forActivity($activityId, 'confirmed'));
    }

    /**
     * The confirmed persons with a dietary need, in assignment order. A draft person still counts (R-10).
     *
     * @return list<array{name: string, dietary: string}>
     */
    public function dietary(int $activityId): array
    {
        $needs = [];
        foreach ($this->forActivity($activityId, 'confirmed') as $row) {
            $personId = (int) ($row['meta']['person'][0] ?? 0);
            if ($personId === 0) {
                continue;
            }
            $person = $this->persons->find($personId, 'any');
            if (is_wp_error($person)) {
                continue;
            }
            $dietary = trim((string) ($person->fields['dietary'] ?? ''));
            if ($dietary === '') {
                continue;
            }
            $needs[] = ['name' => (string) $person->post_title, 'dietary' => $dietary];
        }

        return $needs;
    }

    /** @param array<string, mixed> $data */
    private static function normalise(array $data): array
    {
        foreach (['activity', 'person'] as $key) {
            if (array_key_exists($key, $data) && !is_array($data[$key])) {
                $data[$key] = $data[$key] === null || $data[$key] === '' || (int) $data[$key] === 0 ? [] : [(int) $data[$key]];
            }
        }

        return $data;
    }

    /**
     * The cross-field rules, against the record as it will be after the write.
     * A rule judges only a write that touches one of its fields (or a create):
     * wp-admin may have stored a record outside them (R-1), and a later write
     * of another field — the composed title — must still land.
     */
    private function refuse(array $data, ?int $id): ?WP_Error
    {
        $after = $data;
        if ($id !== null) {
            $existing = $this->find($id, 'any');
            if (!is_wp_error($existing)) {
                $after = array_merge($existing->fields, $data);
            }
        }

        $touches = static fn(string ...$keys): bool => $id === null
            || array_intersect_key($data, array_flip($keys)) !== [];

        $activityId = (int) ($after['activity'][0] ?? 0);
        $personId = (int) ($after['person'][0] ?? 0);
        $name = trim((string) ($after['name'] ?? ''));

        if ($touches('person', 'name') && ($personId === 0) === ($name === '')) {
            return new WP_Error('person_or_name', 'An assignment names exactly one of a person or a name.');
        }

        if ($touches('activity') && is_wp_error($this->activities->find($activityId, 'any'))) {
            return new WP_Error('not_an_activity', 'An assignment belongs to an activity.');
        }

        if ($touches('activity', 'person') && $personId !== 0 && $this->duplicate($activityId, $personId, $id)) {
            return new WP_Error('duplicate_assignment', 'This person is already on this activity.');
        }

        return null;
    }

    private function duplicate(int $activityId, int $personId, ?int $except): bool
    {
        $query = ntdst_data()->get(AssignmentModel::POST_TYPE)
            ->where('post_status', 'any')
            ->whereRelated('activity', $activityId)
            ->whereRelated('person', $personId);

        if ($except !== null) {
            $query->whereNotIn('ID', [$except]);
        }

        return $query->count() > 0;
    }
}
