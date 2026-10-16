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

    /**
     * Which of a slug's holders is in `$lang` — the first holder when `$lang` is null, a type Polylang
     * does not translate. Copies carry their Dutch record's slug, and a slug query is singular:
     * WordPress drops a singular query's tax_query, so each holder's language is read instead.
     *
     * @param list<array<string, mixed>> $rows the chain's rows for one post_name
     */
    public static function holderIn(array $rows, ?string $lang): ?int
    {
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id > 0 && ($lang === null || pll_get_post_language($id) === $lang)) {
                return $id;
            }
        }

        return null;
    }
}
