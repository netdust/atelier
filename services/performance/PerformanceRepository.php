<?php

declare(strict_types=1);

namespace Atelier\Performance;

use Atelier\Models\OrganisationModel;
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
     * explicitly (core's own default is 10 — Data.php:1988). `month`
     * overrides upcoming/past outright — that calendar month, ascending,
     * regardless of `$filter->past`.
     *
     * @return list<array<string, mixed>>
     */
    public function calendar(CalendarFilter $filter, ?string $from = null): array
    {
        $today = (string) current_time('Y-m-d');
        $query = ntdst_data()->get(PerformanceModel::POST_TYPE);

        if ($filter->month !== null) {
            [$first, $last] = self::monthBounds($filter->month);
            $query->where('date', ['>=', $first])->where('date', ['<=', $last])->orderBy('date', 'ASC');
            $descending = false;
        } elseif ($filter->past) {
            $lower = $from ?? self::monthsAgo($today, 24);
            $query->where('date', ['<', $today])->where('date', ['>=', $lower])->orderBy('date', 'DESC');
            $descending = true;
        } else {
            $lower = $from ?? $today;
            $query->where('date', ['>=', $lower])->orderBy('date', 'ASC');
            $descending = false;
        }

        if ($filter->production !== null) {
            $query->whereRelated('production', $filter->production);
        }

        if ($filter->audience !== null) {
            $query->where('audience', $filter->audience);
        }

        if ($filter->country !== null) {
            $venueIds = self::venueIdsForCountry($filter->country);
            if ($venueIds === []) {
                return [];
            }

            $query->whereGroup('OR', static function ($group) use ($venueIds): void {
                foreach ($venueIds as $id) {
                    $group->whereRelated('venue', $id);
                }
            });
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

        usort($views, static fn(array $a, array $b): int => self::compare($a, $b, $descending));

        return $views;
    }

    /**
     * The distinct venue `country` values across the upcoming view and the
     * past window — both of `calendar()`'s own publish checks apply, so a
     * draft's venue never surfaces a country nothing published uses
     * (Threat 1). Used both as the `country` allow-list and to populate the
     * form's options.
     *
     * @return list<string>
     */
    public function countries(): array
    {
        $countries = [];

        foreach ([...$this->calendar(CalendarFilter::upcoming()), ...$this->calendar(CalendarFilter::past())] as $view) {
            $country = trim((string) ($view['venue']->fields['country'] ?? ''));
            if ($country !== '') {
                $countries[$country] = true;
            }
        }

        $countries = array_keys($countries);
        sort($countries);

        return $countries;
    }

    /**
     * The form's own option lists. Productions and months come from the
     * current tense's performances only — the tense a visitor is looking at,
     * not whatever else they already picked, so picking one filter never
     * collapses the others' choices.
     *
     * @return array{productions: array<string, string>, countries: list<string>, months: list<string>}
     */
    public function filterOptions(CalendarFilter $filter): array
    {
        $tense = $filter->past ? CalendarFilter::past() : CalendarFilter::upcoming();

        $productions = [];
        $months = [];
        foreach ($this->calendar($tense) as $view) {
            $productions[(string) $view['production']->post_name] = (string) $view['production']->post_title;
            $months[substr((string) $view['date'], 0, 7)] = true;
        }

        $months = array_keys($months);
        sort($months);

        return [
            'productions' => $productions,
            'countries'   => $this->countries(),
            'months'      => $months,
        ];
    }

    /**
     * Primary: date, in the read's own direction. Ties: time, ascending —
     * empty (all-day) sorts first, since '' is a prefix of every non-empty
     * string.
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private static function compare(array $a, array $b, bool $descending): int
    {
        $byDate = strcmp((string) $a['date'], (string) $b['date']) * ($descending ? -1 : 1);

        return $byDate !== 0 ? $byDate : strcmp((string) $a['time'], (string) $b['time']);
    }

    /**
     * `$country` already went through `CalendarFilter::fromQuery()`'s
     * `sanitize_text_field()` (which trims), matching what `countries()`
     * offers. An exact meta-query match would compare that trimmed value
     * against the RAW stored one, so this reads every published venue and
     * trims on read instead — the same normalisation on both sides.
     *
     * @return list<int>
     */
    private static function venueIdsForCountry(string $country): array
    {
        $ids = [];

        foreach (ntdst_data()->get(OrganisationModel::POST_TYPE)->withMeta()->limit(-1)->get() as $row) {
            if (trim((string) ($row['meta']['country'] ?? '')) === $country) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        }

        return $ids;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function monthBounds(string $month): array
    {
        $first = (new DateTimeImmutable($month . '-01'));

        return [$first->format('Y-m-d'), $first->modify('last day of this month')->format('Y-m-d')];
    }

    private static function monthsAgo(string $date, int $months): string
    {
        return (new DateTimeImmutable($date))->modify("-{$months} months")->format('Y-m-d');
    }
}
