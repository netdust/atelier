<?php

declare(strict_types=1);

namespace Atelier\Support;

defined('ABSPATH') || exit;

/** A service's own file as the browser fetches it. */
final class Asset
{
    /** `$relative` to the directory of `$baseFile`, versioned by filemtime() so an edit invalidates itself. */
    public static function url(string $baseFile, string $relative): string
    {
        return add_query_arg('ver', (string) filemtime(dirname($baseFile) . '/' . $relative), plugins_url($relative, $baseFile));
    }
}
