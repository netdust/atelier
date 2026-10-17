<?php

declare(strict_types=1);

namespace Atelier\Support;

defined('ABSPATH') || exit;

/**
 * The one rule a stored attachment id is read back by, a kit's file rows and the company profile's
 * media alike: it is live while WordPress still gives it a URL. A row the page cannot link is no row.
 */
final class Attachment
{
    /** The attachment's URL; null when `$id` names none — 0 included, never the global post. */
    public static function url(mixed $id): ?string
    {
        $id = is_numeric($id) ? absint($id) : 0;
        $url = $id > 0 ? wp_get_attachment_url($id) : false;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
