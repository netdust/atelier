<?php

declare(strict_types=1);

namespace Atelier\Planning;

use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/** The one place `absence` data is read or written. */
final class AbsenceRepository
{
    public function find(int $id, string $status = 'publish'): WP_Post|WP_Error
    {
        return ntdst_data()->get(AbsenceModel::POST_TYPE)->find($id, $status);
    }

    /** `person` may be given as an int. */
    public function create(array $data): WP_Post|WP_Error
    {
        $data = self::normalise($data);

        if (self::toBeforeFrom((string) ($data['from'] ?? ''), (string) ($data['to'] ?? ''))) {
            return self::refused();
        }

        return ntdst_data()->get(AbsenceModel::POST_TYPE)->create($data);
    }

    /** One end given alone is checked against the stored other end. */
    public function update(int $id, array $data): WP_Post|WP_Error
    {
        $data = self::normalise($data);

        if (array_key_exists('from', $data) || array_key_exists('to', $data)) {
            $existing = $this->find($id, 'any');
            $from = (string) ($data['from'] ?? (is_wp_error($existing) ? '' : $existing->fields['from']));
            $to = (string) ($data['to'] ?? (is_wp_error($existing) ? '' : $existing->fields['to']));
            if (self::toBeforeFrom($from, $to)) {
                return self::refused();
            }
        }

        return ntdst_data()->get(AbsenceModel::POST_TYPE)->update($id, $data);
    }

    /** To the trash, never past it; an id of another type is core's `not_found`, as on update(). */
    public function delete(int $id): bool|WP_Error
    {
        return ntdst_data()->get(AbsenceModel::POST_TYPE)->delete($id);
    }

    /**
     * The absences touching a window, both ends included, earliest first.
     *
     * @return list<array<string, mixed>> query rows: post columns at the top level, the fields under `meta`
     */
    public function overlapping(string $from, string $to, ?int $personId = null): array
    {
        $query = ntdst_data()->get(AbsenceModel::POST_TYPE)
            ->where('from', ['<=', $to])
            ->where('to', ['>=', $from])
            ->orderBy('from', 'ASC')
            ->limit(-1)
            ->withMeta();

        if ($personId !== null) {
            $query->whereRelated('person', $personId);
        }

        return array_values($query->get());
    }

    /** Both are `Y-m-d`, so the string order is the date order; an empty end is core's `required` to refuse. */
    private static function toBeforeFrom(string $from, string $to): bool
    {
        return $from !== '' && $to !== '' && strcmp($to, $from) < 0;
    }

    private static function refused(): WP_Error
    {
        return new WP_Error('to_before_from', 'An absence ends on or after the day it starts.');
    }

    /** @param array<string, mixed> $data */
    private static function normalise(array $data): array
    {
        if (array_key_exists('person', $data) && !is_array($data['person'])) {
            $data['person'] = $data['person'] === null || $data['person'] === '' || (int) $data['person'] === 0 ? [] : [(int) $data['person']];
        }

        return $data;
    }
}
