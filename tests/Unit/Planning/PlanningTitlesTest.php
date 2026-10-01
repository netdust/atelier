<?php

declare(strict_types=1);

namespace AtelierTests\Unit\Planning;

use Atelier\Planning\PlanningTitles;
use PHPUnit\Framework\TestCase;

/** The titles an assignment and an absence carry: composed, never typed. */
final class PlanningTitlesTest extends TestCase
{
    public function test_assignment_title_shape(): void
    {
        $this->assertSame(
            'Jotka Bauwens — Lunch bistrot (2026-05-18)',
            PlanningTitles::assignment('Jotka Bauwens', 'Lunch bistrot', '2026-05-18'),
        );
    }

    public function test_absence_title_collapses_one_day(): void
    {
        $this->assertSame('Sara Sampelayo 2026-01-05', PlanningTitles::absence('Sara Sampelayo', '2026-01-05', '2026-01-05'));
        $this->assertSame('Sara Sampelayo 2026-01-05 – 2026-01-09', PlanningTitles::absence('Sara Sampelayo', '2026-01-05', '2026-01-09'));
    }
}
