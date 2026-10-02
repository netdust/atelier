<?php

declare(strict_types=1);

namespace Atelier\Profile;

use ntdst\baseline\services\polylang\SharedSlugs;
use WP_Error;
use WP_Post;

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
        $lang = self::translatedLanguage();

        // Named explicitly: Polylang narrows an unqualified query to the current (or admin-filter) language.
        if ($lang !== null) {
            $query->whereTax('language', $lang);
        }

        $post = $query->first();

        return $post === null ? null : (int) $post->ID;
    }

    private const SOCIAL = ['website', 'instagram', 'facebook', 'youtube', 'vimeo', 'linkedin'];

    /**
     * The published profile in `$lang` (null: Polylang's current language, else
     * the default); a language without a published copy reads the Dutch record.
     * Copies hold the Dutch facts through baseline's field_sync.
     *
     * @return array<string, mixed>|null
     */
    public function current(?string $lang = null): ?array
    {
        $default = self::translatedLanguage();

        if ($default === null) {
            return $this->shape($this->published(null));
        }

        $lang ??= (function_exists('pll_current_language') ? pll_current_language() : null) ?: $default;
        $post = $lang === $default ? null : $this->published($lang);

        return $this->shape($post ?? $this->published($default));
    }

    private function published(?string $lang): ?WP_Post
    {
        $query = ntdst_data()->get(ProfileModel::POST_TYPE)->orderBy('ID', 'ASC');

        if ($lang !== null) {
            $query->whereTax('language', $lang);
        }

        return $query->first();
    }

    /** @return array<string, mixed>|null */
    private function shape(?WP_Post $post): ?array
    {
        if ($post === null) {
            return null;
        }

        $f = (array) $post->fields;
        $text = static fn(string $key): string => is_scalar($f[$key] ?? null) ? trim((string) $f[$key]) : '';
        $media = static fn(string $key): ?int => absint($f[$key] ?? 0) ?: null;

        return [
            'id'                 => (int) $post->ID,
            'name'               => (string) $post->post_title,
            'legal_name'         => $text('legal_name'),
            'tagline'            => $text('tagline'),
            'bio_short'          => $text('bio_short'),
            'bio_long'           => $text('bio_long'),
            'address'            => [
                'street'   => $text('address_street'),
                'postcode' => $text('address_postcode'),
                'city'     => $text('address_city'),
                'country'  => $text('address_country'),
            ],
            'contacts'           => self::contacts($f['contacts'] ?? []),
            'logo'               => $media('logo'),
            'press_photo'        => $media('press_photo'),
            'press_photo_credit' => $text('press_photo_credit'),
            'social'             => self::social($f),
            'kit'                => [
                'public'    => !empty($f['kit_public']),
                'text'      => $text('kit_text'),
                'photos'    => self::rows($f['kit_photos'] ?? [], 'image', 'credit'),
                'documents' => self::rows($f['kit_documents'] ?? [], 'file', 'name'),
            ],
            'url'                => function_exists('pll_home_url') ? pll_home_url((string) pll_get_post_language($post->ID)) : home_url('/'),
        ];
    }

    /** daan's rule: a row needs a role and a way to reach someone, so a half-filled row never renders. */
    private static function contacts(mixed $rows): array
    {
        $contacts = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $read = static fn(string $key): string => is_array($row) && is_scalar($row[$key] ?? null) ? trim((string) $row[$key]) : '';
            $contact = ['role' => $read('role'), 'name' => $read('name'), 'email' => $read('email'), 'phone' => $read('phone')];

            if ($contact['role'] !== '' && ($contact['email'] !== '' || $contact['phone'] !== '')) {
                $contacts[] = $contact;
            }
        }

        return $contacts;
    }

    /** Only http(s): a stored `javascript:` or a bare host never reaches a link or the schema's sameAs. */
    private static function social(array $fields): array
    {
        $links = [];

        foreach (self::SOCIAL as $key) {
            $url = is_string($fields[$key] ?? null) ? trim($fields[$key]) : '';

            if (in_array(wp_parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                $links[$key] = $url;
            }
        }

        return $links;
    }

    /** Repeater rows with an attachment, as `[$media => int, $label => string]`; a row without one is dropped. */
    private static function rows(mixed $rows, string $media, string $label): array
    {
        $kept = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = is_array($row) ? absint($row[$media] ?? 0) : 0;

            if ($id > 0) {
                $kept[] = [$media => $id, $label => is_scalar($row[$label] ?? null) ? trim((string) $row[$label]) : ''];
            }
        }

        return $kept;
    }

    /** Published, in the default language; the title is the company name. */
    public function createDutch(string $name): int|WP_Error
    {
        $lang = self::translatedLanguage();

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

    /** The default language when Polylang translates the profile; null when the record has no language (and no copies). */
    public static function translatedLanguage(): ?string
    {
        if (!function_exists('pll_is_translated_post_type') || !pll_is_translated_post_type(ProfileModel::POST_TYPE)) {
            return null;
        }

        $lang = pll_default_language();

        return is_string($lang) && $lang !== '' ? $lang : null;
    }
}
