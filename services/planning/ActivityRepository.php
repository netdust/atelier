<?php

declare(strict_types=1);

namespace Atelier\Planning;

use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/** The one place `activity` data is read or written. */
final class ActivityRepository
{
    public function find(int $id, string $status = 'publish'): WP_Post|WP_Error
    {
        return ntdst_data()->get(ActivityModel::POST_TYPE)->find($id, $status);
    }

    /** Relations may be given as ints; `status` defaults to the first of the vocabulary. */
    public function create(array $data): WP_Post|WP_Error
    {
        $data = self::normalise($data);
        $data['status'] ??= ActivityModel::STATUSES[0];

        if (trim((string) ($data['title'] ?? '')) === '') {
            return self::titleRequired();
        }

        return ntdst_data()->get(ActivityModel::POST_TYPE)->create($data);
    }

    public function update(int $id, array $data): WP_Post|WP_Error
    {
        $data = self::normalise($data);

        if (array_key_exists('title', $data) && trim((string) $data['title']) === '') {
            return self::titleRequired();
        }

        return ntdst_data()->get(ActivityModel::POST_TYPE)->update($id, $data);
    }

    /**
     * The activities of a date window, both ends included, by date then start
     * (an all-day activity before a timed one), then by creation.
     *
     * @param array{production?: int, kind?: string, status?: string, location?: int, ids?: list<int>} $filter
     * @return list<array<string, mixed>> query rows: post columns at the top level, the fields under `meta`
     */
    public function between(string $from, string $to, array $filter = []): array
    {
        $query = ntdst_data()->get(ActivityModel::POST_TYPE)
            ->where('date', ['>=', $from])
            ->where('date', ['<=', $to])
            ->orderBy('ID', 'ASC')
            ->limit(-1)
            ->withMeta();

        foreach (['kind', 'status'] as $key) {
            if (isset($filter[$key])) {
                $query->where($key, (string) $filter[$key]);
            }
        }
        foreach (['production', 'location'] as $key) {
            if (isset($filter[$key])) {
                $query->whereRelated($key, (int) $filter[$key]);
            }
        }
        if (isset($filter['ids'])) {
            if ($filter['ids'] === []) {
                return [];
            }
            $query->whereIn('ID', $filter['ids']);
        }

        $rows = array_values($query->get());
        usort($rows, [self::class, 'compare']);

        return $rows;
    }

    /** Date first, start second — never start alone, which would reorder across days. */
    private static function compare(array $a, array $b): int
    {
        return strcmp((string) $a['meta']['date'], (string) $b['meta']['date'])
            ?: strcmp((string) $a['meta']['start'], (string) $b['meta']['start']);
    }

    /** @param array<string, mixed> $data */
    private static function normalise(array $data): array
    {
        foreach (['production', 'performance', 'location'] as $key) {
            if (array_key_exists($key, $data) && !is_array($data[$key])) {
                $data[$key] = $data[$key] === null || $data[$key] === '' || (int) $data[$key] === 0 ? [] : [(int) $data[$key]];
            }
        }

        return $data;
    }

    private static function titleRequired(): WP_Error
    {
        return new WP_Error('title_required', 'An activity has a label.');
    }
}
