<?php

declare(strict_types=1);

namespace Atelier\Support;

defined('ABSPATH') || exit;

/**
 * The registration every public atelier model shares: a site's labels and
 * slug, the fields atelier locks, and the once-per-change rewrite flush.
 * Not in REST — nothing reads /wp/v2 yet, so nothing is published there.
 */
final class PublicModel
{
    /**
     * @param array{slug: string, archive: string|false, labels: array} $config the service config after site overrides
     * @param array<string, array> $fields
     * @param list<string> $locked
     * @param array<string, mixed> $args extra register args (supports, taxonomies, menu_icon)
     */
    public static function register(string $postType, string $prefix, array $config, array $fields, array $locked, array $args = []): void
    {
        LockedFields::guard($postType, $fields, $locked);
        RewriteGuard::track($postType, $config['slug'], (string) $config['archive']);

        ntdst_data()->register($postType, array_merge([
            'meta_prefix'        => $prefix,
            // core registers the WordPress post type only when `label` is set
            'label'              => $config['labels']['name'],
            'labels'             => Labels::expand($config['labels']),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_rest'       => false,
            'has_archive'        => $config['archive'],
            'rewrite'            => ['slug' => $config['slug'], 'with_front' => false],
            'fields'             => $fields,
        ], $args));
    }

    /** A taxonomy the builder can filter on, with no public archive of its own. */
    public static function taxonomy(string $plural, string $singular, array $terms = []): array
    {
        return [
            'labels'             => ['name' => $plural, 'singular_name' => $singular],
            'publicly_queryable' => false,
            'rewrite'            => false,
            'show_in_rest'       => false,
            'show_admin_column'  => true,
            'terms'              => $terms,
        ];
    }
}
