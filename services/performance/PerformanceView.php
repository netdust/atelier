<?php

declare(strict_types=1);

namespace Atelier\Performance;

use Atelier\Models\OrganisationRepository;
use Atelier\Production\ProductionRepository;
use DateTimeImmutable;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * A performance's public shape. Every public output reads through here, never `->fields` wholesale,
 * so the private fields backstage-schedule adds to this post (D8) have no way out.
 */
final class PerformanceView
{
    public const KEYS = [
        'id', 'date', 'time', 'start', 'all_day', 'production', 'venue',
        'audience', 'language_version', 'ticket_url', 'ticket_status',
    ];

    /**
     * @return array<string, mixed>|null null when the production or venue isn't published, or the
     *         stored date/time doesn't parse — one bad row must not take the calendar down.
     */
    public static function from(WP_Post $performance): ?array
    {
        $fields = $performance->fields ?? [];

        $production = ntdst_get(ProductionRepository::class)->find(self::relatedId($fields, 'production'));
        if (is_wp_error($production)) {
            return null;
        }

        $venue = ntdst_get(OrganisationRepository::class)->find(self::relatedId($fields, 'venue'));
        if (is_wp_error($venue)) {
            return null;
        }

        $date = (string) ($fields['date'] ?? '');
        $time = (string) ($fields['time'] ?? '');
        $allDay = $time === '';

        $start = self::start($date, $time);
        if ($start === null) {
            return null;
        }

        return [
            'id'               => (int) $performance->ID,
            'date'             => $date,
            'time'             => $time,
            'start'            => $start,
            'all_day'          => $allDay,
            'production'       => $production,
            'venue'            => $venue,
            'audience'         => (string) ($fields['audience'] ?? ''),
            'language_version' => (string) ($fields['language_version'] ?? ''),
            'ticket_url'       => (string) ($fields['ticket_url'] ?? ''),
            'ticket_status'    => (string) ($fields['ticket_status'] ?? ''),
        ];
    }

    /** Round-tripped, so an overflowing `2026-02-31` or `25:00` is refused too. */
    private static function start(string $date, string $time): ?DateTimeImmutable
    {
        [$format, $value] = $time === '' ? ['Y-m-d', $date] : ['Y-m-d H:i', $date . ' ' . $time];
        $start = DateTimeImmutable::createFromFormat('!' . $format, $value, wp_timezone());

        return $start !== false && $start->format($format) === $value ? $start : null;
    }

    /**
     * A relation is stored as an int list even for a single pick (FieldTypes.php:442-444).
     *
     * @param array<string, mixed> $fields
     */
    private static function relatedId(array $fields, string $field): int
    {
        $value = $fields[$field] ?? [];

        return (int) (is_array($value) ? ($value[0] ?? 0) : $value);
    }
}
