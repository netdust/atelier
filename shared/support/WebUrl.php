<?php

declare(strict_types=1);

namespace Atelier\Support;

defined('ABSPATH') || exit;

/**
 * The one rule for a link a page prints from an editor's URL: http(s) with a
 * host, so `javascript:`, a bare host or `http:foo` never becomes a link.
 */
final class WebUrl
{
    public static function is(string $url): bool
    {
        $parts = wp_parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== '';
    }
}
