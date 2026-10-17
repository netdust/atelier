<?php

declare(strict_types=1);

namespace Atelier\Support;

defined('ABSPATH') || exit;

/** WordPress's own no-cache set as header lines, for a route that sends its headers through a seam. */
final class NoCache
{
    /** @return list<string> */
    public static function headers(): array
    {
        $lines = [];

        // Last-Modified arrives as false, WordPress's "remove it": nothing on these routes sends one.
        foreach (array_filter(wp_get_nocache_headers()) as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return $lines;
    }
}
