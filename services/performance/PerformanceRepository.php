<?php

declare(strict_types=1);

namespace Atelier\Performance;

use DateTimeImmutable;
use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/** The one place `performance` data is read or written. */
final class PerformanceRepository
{
    public function find(int $id): WP_Post|WP_Error
    {
        return ntdst_data()->get(PerformanceModel::POST_TYPE)->find($id);
    }

    public function create(array $data): WP_Post|WP_Error
    {
        return ntdst_data()->get(PerformanceModel::POST_TYPE)->create($data);
    }

    public function update(int $id, array $data): WP_Post|WP_Error
    {
        return ntdst_data()->get(PerformanceModel::POST_TYPE)->update($id, $data);
    }

    /**
     * Upcoming or past performances as public views. Today is
     * `current_time('Y-m-d')` — the site's own timezone. `$from` overrides
     * the lower bound (the feed, Task 2); every call sets its limit
     * explicitly (core's own default is 10 — Data.php:1988).
     *
     * @return list<array<string, mixed>>
     */
    public function calendar(CalendarFilter $filter, ?string $from = null): array
    {
        $today = (string) current_time('Y-m-d');
        $query = ntdst_data()->get(PerformanceModel::POST_TYPE);

        if ($filter->past) {
            $lower = $from ?? self::monthsAgo($today, 24);
            $query->where('date', ['<', $today])->where('date', ['>=', $lower])->orderBy('date', 'DESC');
        } else {
            $lower = $from ?? $today;
            $query->where('date', ['>=', $lower])->orderBy('date', 'ASC');
        }

        $views = [];
        foreach ($query->limit(-1)->get() as $row) {
            $post = $this->find((int) ($row['id'] ?? 0));
            if (is_wp_error($post)) {
                continue;
            }

            $view = PerformanceView::from($post);
            if ($view !== null) {
                $views[] = $view;
            }
        }

        usort($views, static fn(array $a, array $b): int => self::compare($a, $b, $filter->past));

        return $views;
    }

    /**
     * Primary: date, in the filter's own direction. Ties: time, ascending —
     * empty (all-day) sorts first, since '' is a prefix of every non-empty
     * string.
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private static function compare(array $a, array $b, bool $past): int
    {
        $byDate = strcmp((string) $a['date'], (string) $b['date']) * ($past ? -1 : 1);

        return $byDate !== 0 ? $byDate : strcmp((string) $a['time'], (string) $b['time']);
    }

    private static function monthsAgo(string $date, int $months): string
    {
        return (new DateTimeImmutable($date))->modify("-{$months} months")->format('Y-m-d');
    }
}
