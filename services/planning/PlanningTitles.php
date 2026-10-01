<?php

declare(strict_types=1);

namespace Atelier\Planning;

defined('ABSPATH') || exit;

/** The titles assignments and absences carry: composed from their fields, never typed. */
final class PlanningTitles
{
    public static function assignment(string $who, string $activityTitle, string $date): string
    {
        return sprintf('%s — %s (%s)', $who, $activityTitle, $date);
    }

    public static function absence(string $who, string $from, string $to): string
    {
        return $from === $to
            ? sprintf('%s %s', $who, $from)
            : sprintf('%s %s – %s', $who, $from, $to);
    }
}
