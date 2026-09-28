<?php

/**
 * The two production lists as builder queries. Each returns baseline's own
 * Production type; resolvers are top-level functions because YOOtheme stores
 * the callable's NAME in its serialized schema (a closure white-screens it).
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace Atelier\Production;

use WP_Post;

defined('ABSPATH') || exit;

\YOOtheme\Event::on('source.init', static function ($source): void {
    $source->queryType(['fields' => [
        'touringProductions'    => [
            'type'       => ['listOf' => 'Production'],
            'metadata'   => ['label' => 'Touring productions', 'group' => 'Atelier'],
            'extensions' => ['call' => ['func' => __NAMESPACE__ . '\\resolve_touring']],
        ],
        'previouslyProductions' => [
            'type'       => ['listOf' => 'Production'],
            'metadata'   => ['label' => 'All productions (previously)', 'group' => 'Atelier'],
            'extensions' => ['call' => ['func' => __NAMESPACE__ . '\\resolve_previously']],
        ],
    ]]);
}, -10);

/** @return list<WP_Post> */
function resolve_touring($root, array $args): array
{
    return ntdst_get(ProductionRepository::class)->touring();
}

/** @return list<WP_Post> */
function resolve_previously($root, array $args): array
{
    return ntdst_get(ProductionRepository::class)->previously();
}
