<?php

declare(strict_types=1);

namespace AtelierTests\Integration\Performance;

use Atelier\Models\OrganisationRepository;
use Atelier\Performance\CalendarFilter;
use Atelier\Performance\PerformanceModel;
use Atelier\Performance\PerformanceRepository;
use Atelier\Performance\PerformanceView;
use Atelier\Production\ProductionRepository;
use WP_UnitTestCase;

/**
 * PerformanceRepository::calendar(): upcoming ascending from today, past
 * descending before it, both read in the site's own timezone, and never a
 * performance whose production is unpublished.
 */
final class CalendarReadTest extends WP_UnitTestCase
{
    private const CONFIG = [
        'slug'     => 'performances',
        'archive'  => false,
        'calendar' => 'calendar',
        'labels'   => ['name' => 'Performances', 'singular_name' => 'Performance'],
    ];

    public function set_up(): void
    {
        parent::set_up();

        PerformanceModel::register(apply_filters('ntdst/service/performance/config', self::CONFIG));
    }

    private function repository(): PerformanceRepository
    {
        return ntdst_get(PerformanceRepository::class);
    }

    private function production(string $status = 'publish'): int
    {
        $created = ntdst_get(ProductionRepository::class)->create([
            'title'         => 'Wat de koe zag',
            'premiere_year' => 2019,
            'kind'          => 'premiere',
            'post_status'   => $status,
        ]);
        $this->assertNotWPError($created);

        return $created->ID;
    }

    private function venue(string $country = ''): int
    {
        $created = ntdst_get(OrganisationRepository::class)->create([
            'title'       => 'CC Foo',
            'country'     => $country,
            'post_status' => 'publish',
        ]);
        $this->assertNotWPError($created);

        return $created->ID;
    }

    private function performance(
        string $date,
        string $time = '',
        ?int $production = null,
        ?int $venue = null,
        string $audience = 'public',
    ): int {
        $created = $this->repository()->create([
            'title'       => 'Speeldatum',
            'production'  => [$production ?? $this->production()],
            'venue'       => [$venue ?? $this->venue()],
            'date'        => $date,
            'time'        => $time,
            'audience'    => $audience,
            'post_status' => 'publish',
        ]);
        $this->assertNotWPError($created);

        return $created->ID;
    }

    public function test_upcoming_is_ascending_from_today(): void
    {
        $today = (string) current_time('Y-m-d');
        $middle = gmdate('Y-m-d', strtotime($today . ' +5 days'));
        $later = gmdate('Y-m-d', strtotime($today . ' +10 days'));

        $this->performance($later);
        $this->performance($today);
        $this->performance($middle);

        $views = $this->repository()->calendar(CalendarFilter::upcoming());

        $this->assertSame([$today, $middle, $later], array_column($views, 'date'));
    }

    public function test_upcoming_includes_today_in_site_timezone(): void
    {
        update_option('timezone_string', 'Europe/Brussels');
        $today = (string) current_time('Y-m-d');

        $this->performance($today);

        $views = $this->repository()->calendar(CalendarFilter::upcoming());

        $this->assertContains($today, array_column($views, 'date'));
    }

    public function test_past_is_descending_before_today(): void
    {
        $today = (string) current_time('Y-m-d');
        $yesterday = gmdate('Y-m-d', strtotime($today . ' -1 day'));
        $lastWeek = gmdate('Y-m-d', strtotime($today . ' -7 days'));

        $this->performance($today);
        $this->performance($lastWeek);
        $this->performance($yesterday);

        $views = $this->repository()->calendar(CalendarFilter::fromQuery(['past' => '1']));

        $this->assertSame([$yesterday, $lastWeek], array_column($views, 'date'));
    }

    public function test_calendar_hides_performances_of_unpublished_productions(): void
    {
        $today = (string) current_time('Y-m-d');
        $draftProduction = $this->production('draft');
        $this->performance($today, '', $draftProduction);

        $views = $this->repository()->calendar(CalendarFilter::upcoming());

        $this->assertSame([], $views);

        // Threat 1: an unpublished production's venue must not leak into the
        // country list either — a "Japan" nobody else uses would out a draft
        // tour in the filter options.
        $onlyDraftVenue = $this->venue('Japan');
        $this->performance($today, '', $this->production('draft'), $onlyDraftVenue);

        $this->assertNotContains('Japan', $this->repository()->countries());
    }

    public function test_country_filters_through_the_venue(): void
    {
        $today = (string) current_time('Y-m-d');
        $belgium = $this->venue('Belgium');
        $netherlands = $this->venue('Netherlands');
        $this->performance($today, '', null, $belgium);
        $this->performance($today, '', null, $netherlands);

        $views = $this->repository()->calendar(CalendarFilter::fromQuery(['country' => 'Belgium']));

        $this->assertCount(1, $views);
        $this->assertSame('Belgium', $views[0]['venue']->fields['country']);
    }

    /**
     * `countries()` trims the stored value before offering it, so filtering
     * on a country a visitor was actually offered must match the same
     * padded, stored row — not silently return nothing. `create()`'s own
     * `sanitize_text_field()` already trims on write, so the padded value is
     * written directly (a legacy row, or a future write path that doesn't
     * sanitize the same way — "the value comes from the database" is not a
     * reason to trust its shape).
     */
    public function test_country_filter_matches_a_padded_stored_value(): void
    {
        $today = (string) current_time('Y-m-d');
        $padded = $this->venue('Belgium');
        update_post_meta($padded, '_atelier_organisation_country', ' Belgium ');
        $this->performance($today, '', null, $padded);

        $this->assertContains('Belgium', $this->repository()->countries());

        $views = $this->repository()->calendar(CalendarFilter::fromQuery(['country' => 'Belgium']));

        $this->assertCount(1, $views);
    }

    public function test_audience_and_month_combine(): void
    {
        $this->performance('2026-10-05', '', null, null, 'public');
        $this->performance('2026-10-15', '', null, null, 'school');
        $this->performance('2026-11-05', '', null, null, 'public');

        $filter = CalendarFilter::fromQuery(['audience' => 'public', 'month' => '2026-10']);
        $views = $this->repository()->calendar($filter);

        $this->assertSame(['2026-10-05'], array_column($views, 'date'));
    }

    public function test_view_carries_only_public_keys(): void
    {
        $today = (string) current_time('Y-m-d');
        $id = $this->performance($today, '19:30');
        update_post_meta($id, '_atelier_performance_crew', 'secret');

        $views = $this->repository()->calendar(CalendarFilter::upcoming());

        $this->assertCount(1, $views);
        $this->assertSame(PerformanceView::KEYS, array_keys($views[0]));
    }

    public function test_a_performance_at_a_draft_venue_is_absent(): void
    {
        $today = (string) current_time('Y-m-d');
        $draftVenue = ntdst_get(OrganisationRepository::class)->create([
            'title'       => 'CC Draft',
            'post_status' => 'draft',
        ]);
        $this->assertNotWPError($draftVenue);
        $this->performance($today, '', null, $draftVenue->ID);

        $this->assertSame([], $this->repository()->calendar(CalendarFilter::upcoming()));
    }

    public function test_a_corrupt_stored_date_or_time_is_skipped_not_fatal(): void
    {
        $today = (string) current_time('Y-m-d');
        $badTime = $this->performance($today, '20:00');
        update_post_meta($badTime, '_atelier_performance_time', 'garbage');
        $badDate = $this->performance($today, '20:00');
        update_post_meta($badDate, '_atelier_performance_date', 'garbage');
        $good = $this->performance($today, '21:00');

        $this->assertSame([$good], array_column($this->repository()->calendar(CalendarFilter::upcoming()), 'id'));
        $this->assertSame([], array_column($this->repository()->calendar(CalendarFilter::past()), 'id'));
    }

    public function test_an_identical_read_is_served_from_the_request_memo(): void
    {
        global $wpdb;
        $today = (string) current_time('Y-m-d');
        $this->performance($today, '', null, $this->venue('Belgium'));

        $this->repository()->calendar(CalendarFilter::upcoming());
        $this->repository()->countries();
        $queries = $wpdb->num_queries;
        $this->repository()->calendar(CalendarFilter::upcoming());
        $this->repository()->countries();
        $this->assertSame($queries, $wpdb->num_queries);

        $this->performance($today);
        $views = $this->repository()->calendar(CalendarFilter::upcoming());
        $this->assertCount(2, $views, 'a write invalidates the memo');
    }

    public function test_organisation_ids_by_country_trims_and_reads_published_only(): void
    {
        $padded = $this->venue('Belgium');
        update_post_meta($padded, '_atelier_organisation_country', ' Belgium ');
        $this->venue('France');
        $draft = ntdst_get(OrganisationRepository::class)->create([
            'title'       => 'CC Draft',
            'country'     => 'Belgium',
            'post_status' => 'draft',
        ]);
        $this->assertNotWPError($draft);

        $this->assertSame([$padded], ntdst_get(OrganisationRepository::class)->idsByCountry('Belgium'));
    }
}
