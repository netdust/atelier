<?php

declare(strict_types=1);

namespace Atelier\Models;

use Atelier\Support\PublicModel;

defined('ABSPATH') || exit;

/**
 * One record for venues and partners; its roles are terms, which the builder
 * filters natively (it cannot filter a list on a meta flag).
 */
final class OrganisationModel
{
    public const POST_TYPE = 'organisation';
    public const ROLES = ['venue' => 'Venue', 'coproducer' => 'Co-producer', 'funder' => 'Funder', 'presenter' => 'Presenter'];

    /** Never in REST, never in the builder. */
    public const PRIVATE = ['contacts', 'tech_contact', 'notes'];

    public static function register(array $config): void
    {
        PublicModel::register(self::POST_TYPE, '_atelier_organisation_', $config, self::fields(), [], [
            'supports'   => ['title', 'editor', 'thumbnail'],
            'menu_icon'  => 'dashicons-building',
            'taxonomies' => [
                'organisation_role' => PublicModel::taxonomy('Roles', 'Role', self::ROLES),
            ],
        ]);
    }

    private static function fields(): array
    {
        return [
            'address'      => ['type' => 'text', 'translate' => false],
            'city'         => ['type' => 'text', 'translate' => false],
            'country'      => ['type' => 'text', 'translate' => false],
            'lat'          => ['type' => 'float'],
            'lng'          => ['type' => 'float'],
            'website'      => ['type' => 'url'],
            'contacts'     => ['type' => 'textarea', 'translate' => false],
            'tech_contact' => ['type' => 'textarea', 'translate' => false],
            'notes'        => ['type' => 'textarea', 'translate' => false, 'description' => 'Staff only: loading, parking, access'],
        ];
    }
}
