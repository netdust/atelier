<?php

declare(strict_types=1);

namespace AtelierTests\Unit\Performance;

use Atelier\Performance\PerformanceSources;
use PHPUnit\Framework\TestCase;

/**
 * The builder query's label is the SITE's word, never atelier's own — a site
 * that renames `Performances` to `Speeldata` sees `Speeldata` in the picker.
 */
final class PerformanceSourcesTest extends TestCase
{
    public function test_query_label_and_group_come_from_the_configured_labels(): void
    {
        $fields = PerformanceSources::queries([
            'labels' => ['name' => 'Speeldata', 'singular_name' => 'Speeldatum'],
        ]);

        $this->assertSame('Speeldata', $fields['calendarPerformances']['metadata']['label']);
        $this->assertSame('Speeldata', $fields['calendarPerformances']['metadata']['group']);
    }

    public function test_query_falls_back_to_atelier_own_neutral_label(): void
    {
        $fields = PerformanceSources::queries([]);

        $this->assertSame('Performances', $fields['calendarPerformances']['metadata']['label']);
    }

    /**
     * Task 6: the two fields merged onto YOOtheme's own `Production` type
     * (yootheme.md § the escape hatch — `objectType()` under a name the
     * module already uses MERGES into it). Grouped under the same
     * site-configured label as the calendar query, so both sit together
     * in the picker.
     */
    public function test_production_fields_are_declared_and_grouped(): void
    {
        $fields = PerformanceSources::productionFields([
            'labels' => ['name' => 'Speeldata', 'singular_name' => 'Speeldatum'],
        ]);

        $this->assertArrayHasKey('upcomingPerformances', $fields);
        $this->assertSame(['listOf' => 'Performance'], $fields['upcomingPerformances']['type']);
        $this->assertSame('Speeldata', $fields['upcomingPerformances']['metadata']['group']);
        $this->assertSame(
            'Atelier\\Performance\\resolve_upcoming',
            $fields['upcomingPerformances']['extensions']['call']['func'],
        );

        $this->assertArrayHasKey('calendarFeedUrl', $fields);
        $this->assertSame('String', $fields['calendarFeedUrl']['type']);
        $this->assertSame('Speeldata', $fields['calendarFeedUrl']['metadata']['group']);
        $this->assertSame(
            'Atelier\\Performance\\resolve_calendar_feed_url',
            $fields['calendarFeedUrl']['extensions']['call']['func'],
        );
    }

    public function test_production_fields_fall_back_to_atelier_own_neutral_label(): void
    {
        $fields = PerformanceSources::productionFields([]);

        $this->assertSame('Performances', $fields['upcomingPerformances']['metadata']['group']);
    }
}
