<?php

declare(strict_types=1);

namespace Atelier\Profile;

use Atelier\Support\Languages;
use Atelier\Support\WebUrl;
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
        $lang = Languages::defaultFor(ProfileModel::POST_TYPE);

        // Named explicitly: Polylang narrows an unqualified query to the current (or admin-filter) language.
        if ($lang !== null) {
            $query->whereTax('language', $lang);
        }

        $post = $query->first();

        return $post === null ? null : (int) $post->ID;
    }

    /**
     * The published profile in `$lang` (null: Polylang's current language, else
     * the default): the Dutch record's linked copy, or the Dutch record when that
     * language has no published copy. Copies hold the Dutch facts through baseline's field_sync.
     *
     * @return array<string, mixed>|null
     */
    public function current(?string $lang = null): ?array
    {
        $default = Languages::defaultFor(ProfileModel::POST_TYPE);

        if ($default === null) {
            return $this->shape($this->firstPublished());
        }

        $dutch = $this->dutchId();

        if ($dutch === null) {
            return null;
        }

        $lang ??= (function_exists('pll_current_language') ? pll_current_language() : null) ?: $default;
        $copy = $lang === $default ? null : pll_get_post($dutch, $lang);

        return $this->shape(($copy ? $this->published((int) $copy) : null) ?? $this->published($dutch));
    }

    /** One more download of the company kit on `$id`: the count is a callback field, read back as text, so it is cast. */
    public function countDownload(int $id): true|WP_Error
    {
        $model = ntdst_data()->get(ProfileModel::POST_TYPE);
        $record = $model->find($id);

        // Publish-only: an unpublished record's count would read back as nothing, and restart at 1.
        if (is_wp_error($record)) {
            return $record;
        }

        return $model->updateMeta($id, 'kit_download_count', absint($record->fields['kit_download_count'] ?? 0) + 1);
    }

    private function firstPublished(): ?WP_Post
    {
        return ntdst_data()->get(ProfileModel::POST_TYPE)->orderBy('ID', 'ASC')->first();
    }

    private function published(int $id): ?WP_Post
    {
        $post = ntdst_data()->get(ProfileModel::POST_TYPE)->find($id);

        return $post instanceof WP_Post ? $post : null;
    }

    /** @return array<string, mixed>|null */
    private function shape(?WP_Post $post): ?array
    {
        if ($post === null) {
            return null;
        }

        $f = (array) $post->fields;
        $text = static fn(string $key): string => is_scalar($f[$key] ?? null) ? trim((string) $f[$key]) : '';
        $media = static fn(string $key): ?int => self::attachment($f[$key] ?? 0);

        return [
            'id'                 => (int) $post->ID,
            'name'               => (string) $post->post_title,
            'legal_name'         => $text('legal_name'),
            'tagline'            => $text('tagline'),
            'bio_short'          => $text('bio_short'),
            'bio_long'           => $text('bio_long'),
            'email'              => is_email($text('email')) ? $text('email') : '',
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
            $email = $read('email');
            $contact = ['role' => $read('role'), 'name' => $read('name'), 'email' => is_email($email) ? $email : '', 'phone' => $read('phone')];

            if ($contact['role'] !== '' && ($contact['email'] !== '' || $contact['phone'] !== '')) {
                $contacts[] = $contact;
            }
        }

        return $contacts;
    }

    /** Only http(s) with a host: `javascript:`, a bare host or `http:foo` never reaches a link or sameAs. */
    private static function social(array $fields): array
    {
        $links = [];

        foreach (ProfileModel::SOCIAL as $key) {
            $url = is_string($fields[$key] ?? null) ? trim($fields[$key]) : '';

            if (WebUrl::is($url)) {
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
            $id = is_array($row) ? self::attachment($row[$media] ?? 0) : null;

            if ($id !== null) {
                $kept[] = [$media => $id, $label => is_scalar($row[$label] ?? null) ? trim((string) $row[$label]) : ''];
            }
        }

        return $kept;
    }

    /** An id read back only when it still names an attachment. */
    private static function attachment(mixed $id): ?int
    {
        $id = absint($id);

        return $id > 0 && get_post_type($id) === 'attachment' ? $id : null;
    }

    /** Published, in the default language; the title is the company name, and no user owns it. */
    public function createDutch(string $name): int|WP_Error
    {
        $lang = Languages::defaultFor(ProfileModel::POST_TYPE);

        if ($lang === null) {
            $post = ntdst_data()->get(ProfileModel::POST_TYPE)->create(['title' => $name, 'post_status' => 'publish', 'post_author' => 0]);

            return is_wp_error($post) ? $post : (int) $post->ID;
        }

        return SharedSlugs::withPendingLanguage($lang, static fn () => pll_insert_post([
            'post_type'   => ProfileModel::POST_TYPE,
            'post_status' => 'publish',
            'post_author' => 0,
            'post_title'  => wp_slash($name),
        ], $lang));
    }
}
