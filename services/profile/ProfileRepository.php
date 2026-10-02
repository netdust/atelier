<?php

declare(strict_types=1);

namespace Atelier\Profile;

use ntdst\baseline\services\polylang\SharedSlugs;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * The one place `company_profile` is read or written. Under Polylang the
 * profile is the record in the default language; its copies are translations.
 */
final class ProfileRepository
{
    /** Any status but trash and auto-draft, so a post-new.php draft never counts. */
    public function dutchId(): ?int
    {
        $query = ntdst_data()->get(ProfileModel::POST_TYPE)->where('post_status', 'any')->orderBy('ID', 'ASC');
        $lang = self::language();

        // Named explicitly: Polylang narrows an unqualified query to the current (or admin-filter) language.
        if ($lang !== null) {
            $query->whereTax('language', $lang);
        }

        $post = $query->first();

        return $post === null ? null : (int) $post->ID;
    }

    /** Published, in the default language; the title is the company name. */
    public function createDutch(string $name): int|WP_Error
    {
        $lang = self::language();

        if ($lang === null) {
            $post = ntdst_data()->get(ProfileModel::POST_TYPE)->create(['title' => $name, 'post_status' => 'publish']);

            return is_wp_error($post) ? $post : (int) $post->ID;
        }

        return SharedSlugs::withPendingLanguage($lang, static fn () => pll_insert_post([
            'post_type'   => ProfileModel::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => wp_slash($name),
        ], $lang));
    }

    /** The default language when Polylang translates the profile; otherwise the record has none. */
    private static function language(): ?string
    {
        if (!function_exists('pll_is_translated_post_type') || !pll_is_translated_post_type(ProfileModel::POST_TYPE)) {
            return null;
        }

        $lang = pll_default_language();

        return is_string($lang) && $lang !== '' ? $lang : null;
    }
}
