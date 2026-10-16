<?php

declare(strict_types=1);

namespace Atelier\PressKit;

use Atelier\Production\ProductionModel;
use Atelier\Production\ProductionRepository;
use Atelier\Profile\ProfileModel;
use Atelier\Profile\ProfileRepository;
use Atelier\Support\Languages;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * A kit's view model, a production's or the company's. The text is the record's in the kit's
 * language; the public switch and the files are the Dutch record's, which its copies follow.
 */
final class PressKitModel
{
    public function __construct(
        private readonly ProductionRepository $productions,
        private readonly ProfileRepository $profiles,
    ) {
    }

    /**
     * The company's kit from its profile: the copy in `$lang`, or the Dutch record where that language
     * has none. null until the Dutch profile is published.
     *
     * @return array<string, mixed>|null
     */
    public function forCompany(string $lang): ?array
    {
        $dutch = $this->profiles->current(Languages::defaultFor(ProfileModel::POST_TYPE));
        $record = $this->profiles->current($lang);

        if ($dutch === null || $record === null) {
            return null;
        }

        return [
            'type'      => 'company',
            'id'        => $record['id'],
            'dutch_id'  => $dutch['id'],
            'title'     => $record['name'],
            'text'      => $record['kit']['text'],
            'photos'    => self::files($dutch['kit']['photos'], 'image', 'credit'),
            'logos'     => [],
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
        $production = $default === null
            ? $this->productions->findPublishedBySlug($slug)
            : $this->productions->findPublishedBySlugIn($slug, $lang);

        if ($production === null) {
            return null;
        }

        $dutch = $default === null ? $production : $this->productions->find((int) pll_get_post($production->ID, $default));

        if (!$dutch instanceof WP_Post) {
            return null;
        }

        return self::kit('production', $production, $dutch);
    }

    /** @return array<string, mixed> */
    private static function kit(string $type, WP_Post $record, WP_Post $dutch): array
    {
        $facts = (array) $dutch->fields;

        return [
            'type'      => $type,
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
     * Repeater rows whose attachment still exists, as `['url' => …, $label => …]`; a row without one is dropped.
     *
     * @return list<array<string, string>>
     */
    private static function files(mixed $rows, string $media, string $label): array
    {
        $files = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $url = is_array($row) ? wp_get_attachment_url(absint($row[$media] ?? 0)) : false;

            if (!is_string($url) || $url === '') {
                continue;
            }

            $files[] = ['url' => $url, $label => is_scalar($row[$label] ?? null) ? trim((string) $row[$label]) : ''];
        }

        return $files;
    }
}
