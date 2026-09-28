<?php

declare(strict_types=1);

namespace Atelier\Support;

defined('ABSPATH') || exit;

/**
 * Service overrides merge shallowly (core Bootstrap: array_merge), so a site
 * that relabels a model replaces the whole labels array. Everything WordPress
 * needs is derived from the two names; any key the site gave wins.
 */
final class Labels
{
    public static function expand(array $labels): array
    {
        $plural = (string) ($labels['name'] ?? '');
        $singular = (string) ($labels['singular_name'] ?? $plural);

        return array_merge([
            'name'               => $plural,
            'singular_name'      => $singular,
            'menu_name'          => $plural,
            'all_items'          => $plural,
            'add_new'            => sprintf('%s +', $singular),
            'add_new_item'       => $singular,
            'edit_item'          => $singular,
            'new_item'           => $singular,
            'view_item'          => $singular,
            'view_items'         => $plural,
            'search_items'       => $plural,
            'not_found'          => '—',
            'not_found_in_trash' => '—',
        ], $labels);
    }
}
