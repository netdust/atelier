<?php

declare(strict_types=1);

namespace Atelier\Support;

use NTDST_Data_Model;
use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * A kit's download count on its edit screen — a production's, the company profile's. A `callback`
 * field: it draws itself as text and core's metabox save skips it, so an editor's save never writes
 * back the count the screen was opened with. Only the kit writes it, through increment().
 */
final class DownloadCount
{
    /** @return array{type: string, callback: callable} */
    public static function field(): array
    {
        return ['type' => 'callback', 'callback' => [self::class, 'render']];
    }

    /** One more download of the kit on `$id`: the count is a callback field, read back as text, so it is cast. */
    public static function increment(NTDST_Data_Model $model, int $id): true|WP_Error
    {
        $record = $model->find($id);

        // Publish-only: an unpublished record's count would read back as nothing, and restart at 1.
        if (is_wp_error($record)) {
            return $record;
        }

        return $model->updateMeta($id, 'kit_download_count', absint($record->fields['kit_download_count'] ?? 0) + 1);
    }

    /** The count as its sibling fields are drawn, with no input to post. */
    public static function render(?WP_Post $post, string $name, mixed $value): void
    {
        echo '<div class="ntdst-field"><label>' . esc_html(ucwords(str_replace('_', ' ', $name))) . '</label>';
        echo '<div class="ntdst-readonly-value"><strong>' . esc_html((string) absint($value)) . '</strong></div></div>';
    }
}
