<?php

declare(strict_types=1);

namespace Atelier\Support;

defined('ABSPATH') || exit;

/**
 * Which language a record is read in when Polylang translates its type: the
 * default one, which holds the record its copies translate.
 */
final class Languages
{
    /** The default language when Polylang translates $postType; null when it does not, so records carry no language. */
    public static function defaultFor(string $postType): ?string
    {
        if (!function_exists('pll_is_translated_post_type') || !pll_is_translated_post_type($postType)) {
            return null;
        }

        $lang = pll_default_language();

        return is_string($lang) && $lang !== '' ? $lang : null;
    }
}
