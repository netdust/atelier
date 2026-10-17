<?php

declare(strict_types=1);

namespace Atelier\PressKit;

use Atelier\Production\ProductionModel;
use Atelier\Production\ProductionRepository;
use Atelier\Profile\ProfileModel;
use Atelier\Profile\ProfileRepository;
use Atelier\Support\Attachment;
use Atelier\Support\Languages;
use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * A kit's view model, a production's or the company's. The text is the record's in the kit's
 * language; the public switch and the files are the Dutch record's, which its copies follow.
 */
final class PressKitModel
{
    /** The records a kit is held by: a production's, and the company profile's. */
    public const TYPES = [ProductionModel::POST_TYPE, ProfileModel::POST_TYPE];

    public function __construct(
        private readonly ProductionRepository $productions,
        private readonly ProfileRepository $profiles,
    ) {
    }

    /**
     * The company's kit from its profile: the text is the copy's in `$lang`, or the Dutch record's where
     * that language has no published copy. The fallback is per record, not per field, so a copy with an
     * empty text stays empty. The Dutch profile's logo is the kit's one logo row, named `$logoName`.
     * null until the Dutch profile is published.
     *
     * @return array<string, mixed>|null
     */
    public function forCompany(string $lang, string $logoName): ?array
    {
        $dutch = $this->profiles->current(Languages::defaultFor(ProfileModel::POST_TYPE));
        $record = $this->profiles->current($lang);

        if ($dutch === null || $record === null) {
            return null;
        }

        return [
            'type'      => 'company',
            'post_type' => ProfileModel::POST_TYPE,
            'id'        => $record['id'],
            'dutch_id'  => $dutch['id'],
            'title'     => $record['name'],
            'text'      => $record['kit']['text'],
            'photos'    => self::files($dutch['kit']['photos'], 'image', 'credit'),
            'logos'     => self::files([['file' => $dutch['logo'], 'name' => $logoName]], 'file', 'name'),
            'documents' => self::files($dutch['kit']['documents'], 'file', 'name'),
            'public'    => $dutch['kit']['public'],
        ];
    }

    /**
     * Only the canonical slug of a production published in `$lang` (its Dutch record published too):
     * `/pers/KOE/` or a slug with no copy in that language is no kit.
     *
     * @return array<string, mixed>|null
     */
    public function forProduction(string $slug, string $lang): ?array
    {
        if ($slug === '' || sanitize_title($slug) !== $slug) {
            return null;
        }

        $default = Languages::defaultFor(ProductionModel::POST_TYPE);
        $production = $this->productions->findPublishedBySlug($slug, $default === null ? null : $lang);

        if ($production === null) {
            return null;
        }

        // A copy with no Dutch record is 0, and find(0) is whatever post is global: never asked.
        $dutchId = $default === null ? (int) $production->ID : (int) pll_get_post($production->ID, $default);
        $dutch = $dutchId > 0 ? $this->productions->find($dutchId) : null;

        if (!$dutch instanceof WP_Post) {
            return null;
        }

        return self::kit('production', ProductionModel::POST_TYPE, $production, $dutch);
    }

    /**
     * The Dutch record a kit binds its grants to, behind `$id` — that record or a copy of it — when it
     * is a production or the company profile, whatever its state; null for anything else.
     */
    public function dutchOf(string $postType, int $id): ?int
    {
        if (!in_array($postType, self::TYPES, true)) {
            return null;
        }

        $default = Languages::defaultFor($postType);
        $dutchId = $id > 0 && $default !== null ? (int) pll_get_post($id, $default) : $id;

        if ($dutchId <= 0) {
            return null;
        }

        $held = $postType === ProfileModel::POST_TYPE
            ? $this->profiles->dutchId() === $dutchId
            : $this->productions->find($dutchId, 'any') instanceof WP_Post;

        return $held ? $dutchId : null;
    }

    /** dutchOf(), when that record is published: a kit that opens, so one a link may be issued or mailed for. */
    public function holder(string $postType, int $id): ?int
    {
        $dutchId = $this->dutchOf($postType, $id);

        if ($dutchId === null) {
            return null;
        }

        $published = $postType === ProductionModel::POST_TYPE
            ? $this->productions->find($dutchId) instanceof WP_Post
            : ($this->profiles->current(Languages::defaultFor($postType))['id'] ?? null) === $dutchId;

        return $published ? $dutchId : null;
    }

    /**
     * R-5: the record a personal link opens in `$lang` — its copy there when that is published, else
     * the Dutch record — with that record's language, slug and title. null when `$dutchId` is no
     * kit's Dutch record.
     *
     * @return array{lang: string, slug: string, title: string}|null
     */
    public function inLanguage(string $postType, int $dutchId, string $lang): ?array
    {
        if ($this->holder($postType, $dutchId) !== $dutchId) {
            return null;
        }

        $default = (string) Languages::defaultFor($postType);
        $lang = in_array($lang, Languages::codes(), true) ? $lang : $default;

        if ($postType === ProfileModel::POST_TYPE) {
            $profile = $this->profiles->current($lang === '' ? null : $lang);

            return $profile === null ? null : [
                'lang'  => $profile['id'] === $dutchId ? $default : $lang,
                'slug'  => '',
                'title' => $profile['name'],
            ];
        }

        // No copy is 0, and get_post(0) is whatever post is global: never asked.
        $copyId = $lang === $default ? 0 : (int) pll_get_post($dutchId, $lang);
        $copy = $copyId > 0 ? $this->productions->find($copyId) : null;
        $record = $copy instanceof WP_Post ? $copy : $this->productions->find($dutchId);

        return $record instanceof WP_Post ? [
            'lang'  => $record === $copy ? $lang : $default,
            'slug'  => (string) $record->post_name,
            'title' => (string) $record->post_title,
        ] : null;
    }

    /** One more download of the kit, on the Dutch record that holds its count. */
    public function countDownload(array $kit): true|WP_Error
    {
        return $kit['type'] === 'company'
            ? $this->profiles->countDownload((int) $kit['dutch_id'])
            : $this->productions->countDownload((int) $kit['dutch_id']);
    }

    /** @return array<string, mixed> */
    private static function kit(string $type, string $postType, WP_Post $record, WP_Post $dutch): array
    {
        $facts = (array) $dutch->fields;

        return [
            'type'      => $type,
            'post_type' => $postType,
            'id'        => (int) $record->ID,
            'dutch_id'  => (int) $dutch->ID,
            'title'     => (string) $record->post_title,
            'text'      => is_string($record->fields['kit_text'] ?? null) ? trim($record->fields['kit_text']) : '',
            'photos'    => self::files($facts['kit_photos'] ?? [], 'image', 'credit'),
            'logos'     => self::files($facts['kit_logos'] ?? [], 'file', 'name'),
            'documents' => self::files($facts['kit_documents'] ?? [], 'file', 'name'),
            'public'    => !empty($facts['kit_public']),
        ];
    }

    /**
     * Repeater rows whose attachment is live, as `['attachment' => id, 'url' => …, $label => …]`;
     * a row without one is dropped.
     *
     * @return list<array<string, int|string>>
     */
    private static function files(mixed $rows, string $media, string $label): array
    {
        $files = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $url = is_array($row) ? Attachment::url($row[$media] ?? 0) : null;

            if ($url === null) {
                continue;
            }

            $files[] = ['attachment' => absint($row[$media]), 'url' => $url, $label => is_scalar($row[$label] ?? null) ? trim((string) $row[$label]) : ''];
        }

        return $files;
    }
}
