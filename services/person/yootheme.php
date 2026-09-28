<?php

/**
 * A person's productions as a field on baseline's own Person type, so it
 * binds on a person's page and inside any list of people. Registering the
 * type name the module already uses merges into it.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace Atelier\Person;

use Atelier\Production\ProductionModel;
use Atelier\Production\ProductionRepository;
use WP_Post;

defined('ABSPATH') || exit;

\YOOtheme\Event::on('source.init', static function ($source): void {
    $roles = ['All roles' => 'all'] + array_flip(ProductionModel::roleFields());

    $source->objectType('Person', ['fields' => [
        'productions' => [
            'type'       => ['listOf' => 'Production'],
            'args'       => ['role' => ['type' => 'String', 'defaultValue' => 'all']],
            'metadata'   => [
                'label'  => 'Productions',
                'group'  => 'Atelier',
                'fields' => ['role' => ['label' => 'Role', 'type' => 'select', 'options' => $roles, 'default' => 'all']],
            ],
            'extensions' => ['call' => ['func' => __NAMESPACE__ . '\\resolve_person_productions']],
        ],
    ]]);
}, -10);

/**
 * Validated by creditedTo(): a role that is not a registered role field
 * answers nothing.
 *
 * @return list<WP_Post>
 */
function resolve_person_productions($root, array $args): array
{
    if (!$root instanceof WP_Post) {
        return [];
    }

    $role = sanitize_key((string) ($args['role'] ?? 'all'));
    $credits = ntdst_get(ProductionRepository::class)->creditedTo($root->ID, $role === 'all' ? null : $role);

    return array_map(static fn(array $credit): WP_Post => $credit['production'], $credits);
}
