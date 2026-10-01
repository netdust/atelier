<?php

declare(strict_types=1);

namespace Atelier\Planning;

use Atelier\Support\Labels;
use Atelier\Support\LockedFields;

defined('ABSPATH') || exit;

/**
 * The registration every planning record shares — PublicModel's counterpart:
 * a wp-admin screen and nothing else. No permalink, no archive, not in
 * search, not in REST; a site relabels through its config, never the key.
 */
final class PrivateModel
{
    /**
     * @param array{labels: array} $config the service config after site overrides
     * @param array<string, array> $fields
     * @param list<string> $locked
     * @param array<string, mixed> $args supports, menu_icon
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
