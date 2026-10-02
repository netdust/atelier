<?php

declare(strict_types=1);

namespace Atelier\Support;

defined('ABSPATH') || exit;

/**
 * The registration every private atelier record shares — PublicModel's counterpart:
 * a wp-admin screen and nothing else. No permalink, no archive, not in
 * search, not in REST; a site relabels through its config, never the key.
 */
final class PrivateModel
{
    /**
     * @param array{labels: array} $config the service config after site overrides
     * @param array<string, array> $fields
     * @param list<string> $locked
     * @param array<string, mixed> $args extra register args (supports, menu_icon, show_in_menu, tabs)
     */
    public static function register(string $postType, string $prefix, array $config, array $fields, array $locked, array $args = []): void
    {
        LockedFields::guard($postType, $fields, $locked);

        ntdst_data()->register($postType, array_merge([
            'meta_prefix'         => $prefix,
            'label'               => $config['labels']['name'],
            'labels'              => Labels::expand($config['labels']),
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_rest'        => false,
            'exclude_from_search' => true,
            'rewrite'             => false,
            'has_archive'         => false,
            'fields'              => $fields,
        ], $args));
    }
}
