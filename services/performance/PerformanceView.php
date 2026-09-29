<?php

declare(strict_types=1);

namespace Atelier\Performance;

use Atelier\Models\OrganisationRepository;
use Atelier\Production\ProductionRepository;
use DateTimeImmutable;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * A performance's public shape — every public output reads a performance
 * through here, never `->fields` wholesale, so a private field Backstage
 * later adds to this same post (D8) has no door out through this one.
 */
final class PerformanceView
{
    public const KEYS = [
        'id', 'date', 'time', 'start', 'all_day', 'production', 'venue',
        'audience', 'language_version', 'ticket_url', 'ticket_status',
    ];

    /**
     * @return array<string, mixed>|null null when the production or the
     *         venue isn't published — the public read is publish-only,
     *         twice over.
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

        return [
            'id'               => (int) $performance->ID,
            'date'             => $date,
            'time'             => $time,
            'start'            => new DateTimeImmutable(trim($date . ' ' . ($allDay ? '00:00' : $time)), wp_timezone()),
            'all_day'          => $allDay,
            'production'       => $production,
            'venue'            => $venue,
            'audience'         => (string) ($fields['audience'] ?? ''),
            'language_version' => (string) ($fields['language_version'] ?? ''),
            'ticket_url'       => (string) ($fields['ticket_url'] ?? ''),
            'ticket_status'    => (string) ($fields['ticket_status'] ?? ''),
        ];
    }

    /**
     * A relation is always stored as an int list, `multiple` or not
     * (FieldTypes.php:442-444) — production and venue are single picks that
     * still arrive as a one-item array.
     *
     * @param array<string, mixed> $fields
     */
    private static function relatedId(array $fields, string $field): int
    {
        $value = $fields[$field] ?? [];

        return (int) (is_array($value) ? ($value[0] ?? 0) : $value);
    }
}
