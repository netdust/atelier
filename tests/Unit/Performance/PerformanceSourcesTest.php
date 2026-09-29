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
}
