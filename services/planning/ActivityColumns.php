<?php

declare(strict_types=1);

namespace Atelier\Planning;

use WP_Post;

defined('ABSPATH') || exit;

/**
 * The Activiteiten list shows an activity's own facts — date, start, kind,
 * status — so two same-titled activities on different days are told apart
 * without opening them. Core's auto-metabox owns the edit screen; this is
 * the one list-screen addition.
 */
final class ActivityColumns
{
    private const COLUMNS = [
        'activity_date' => 'Date',
        'start'         => 'Start',
        'kind'          => 'Kind',
        'status'        => 'Status',
    ];

    public function __construct(private readonly ActivityRepository $activities)
    {
    }

    public function register(): void
    {
        $type = ActivityModel::POST_TYPE;
        add_filter("manage_{$type}_posts_columns", [$this, 'columns']);
        add_action("manage_{$type}_posts_custom_column", [$this, 'cell'], 10, 2);
    }

    /**
     * Our columns sit before WordPress's publish date, which keeps its slot.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function columns(array $columns): array
    {
        $publishDate = $columns['date'] ?? null;
        unset($columns['date']);

        $columns += self::COLUMNS;
        if ($publishDate !== null) {
            $columns['date'] = $publishDate;
        }

        return $columns;
    }

    public function cell(string $column, int $postId): void
    {
        if (!isset(self::COLUMNS[$column])) {
            return;
        }

        $activity = $this->activities->find($postId, 'any');
        if (!$activity instanceof WP_Post) {
            return;
        }

        $field = $column === 'activity_date' ? 'date' : $column;
        echo esc_html((string) ($activity->fields[$field] ?? ''));
    }
}
