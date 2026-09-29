<?php

declare(strict_types=1);

namespace Atelier\Performance;

use Atelier\Models\OrganisationRepository;
use Atelier\Production\ProductionRepository;
use DateTimeImmutable;
use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/** The one place `performance` data is read or written. */
final class PerformanceRepository
{
    /** @var array<string, mixed> One page render reads the same calendar several times. */
    private array $memo = [];

    private string $memoStamp = '';

    public function __construct(
        private readonly OrganisationRepository $organisations,
        private readonly ProductionRepository $productions,
    ) {
    }

    public function find(int $id): WP_Post|WP_Error
    {
        return ntdst_data()->get(PerformanceModel::POST_TYPE)->find($id);
    }

    public function create(array $data): WP_Post|WP_Error
    {
        return ntdst_data()->get(PerformanceModel::POST_TYPE)->create($data);
    }

    /**
     * Upcoming or past performances as public views, "today" in the site's timezone. `$from`
     * overrides the lower bound (the feed); `month` overrides the tense outright.
     *
     * @return list<array<string, mixed>>
     */
    public function calendar(CalendarFilter $filter, ?string $from = null): array
    {
        return $this->memo('calendar:' . serialize([$filter, $from]), fn(): array => $this->readCalendar($filter, $from));
    }

    /**
     * Venue countries across the upcoming view and the past window, so a draft's venue never
     * offers a country nothing published uses (Threat 1). The `country` allow-list and its options.
     *
     * @return list<string>
     */
    public function countries(): array
    {
        return $this->memo('countries', function (): array {
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
        });
    }

    /**
     * Productions and months come from the current tense only, so one pick never collapses the
     * other choices — plus the selected ones, so a pick from the other tense still shows as selected.
     *
     * @return array{productions: array<string, string>, countries: list<string>, months: list<string>, audiences: array<string, string>}
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

        if ($filter->production !== null) {
            $selected = $this->productions->find($filter->production);
            if (!is_wp_error($selected)) {
                $productions[(string) $selected->post_name] ??= (string) $selected->post_title;
            }
        }

        if ($filter->month !== null) {
            $months[$filter->month] = true;
        }

        $months = array_keys($months);
        sort($months);

        return [
            'productions' => $productions,
            'countries'   => $this->countries(),
            'months'      => $months,
            'audiences'   => $this->audienceOptions(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function readCalendar(CalendarFilter $filter, ?string $from): array
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
            $venueIds = $this->organisations->idsByCountry($filter->country);
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
     * The model's own `audience` labels (post-filter, so a site's relabelling counts), scoped to
     * what the filter accepts. An unlabelled value falls back to itself.
     *
     * @return array<string, string>
     */
    private function audienceOptions(): array
    {
        $schema = ntdst_data()->get(PerformanceModel::POST_TYPE)->getSchema();
        $options = is_array($schema['audience']['options'] ?? null) ? $schema['audience']['options'] : [];

        $labels = [];
        foreach (CalendarFilter::audiences() as $value) {
            $labels[$value] = is_string($options[$value] ?? null) ? $options[$value] : $value;
        }

        return $labels;
    }

    /** Any post write bumps `last_changed`, which drops the memo. */
    private function memo(string $key, callable $read): mixed
    {
        $stamp = wp_cache_get_last_changed('posts') . '|' . current_time('Y-m-d');
        if ($stamp !== $this->memoStamp) {
            $this->memo = [];
            $this->memoStamp = $stamp;
        }

        if (!array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $read();
        }

        return $this->memo[$key];
    }

    /**
     * Date in the read's direction, then time ascending — all-day ('') first.
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
