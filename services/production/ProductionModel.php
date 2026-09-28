<?php

declare(strict_types=1);

namespace Atelier\Production;

use Atelier\Organisation\OrganisationModel;
use Atelier\Person\PersonModel;
use Atelier\Support\PublicModel;

defined('ABSPATH') || exit;

final class ProductionModel
{
    public const POST_TYPE = 'production';
    /** Terms of production_stage: a taxonomy, so the builder filters "touring" natively. */
    public const STAGES = ['in_creation' => 'In creation', 'touring' => 'Touring', 'archive' => 'Archive'];
    public const KINDS = ['premiere', 'rerun', 'also'];

    /** Read by atelier's own lists; a site may relabel them, never remove them. */
    public const LOCKED = ['premiere_year', 'kind'];

    /**
     * Credits are one relation field per role: core refuses a relation inside
     * a repeater row. A site adds or relabels roles through
     * ntdst/production/fields; every `credit_` relation to a person is a role
     * (ProductionRepository::roleFields()).
     */
    public const ROLE_PREFIX = 'credit_';

    public static function register(array $config): void
    {
        PublicModel::register(self::POST_TYPE, '_atelier_production_', $config, self::fields(), self::LOCKED, [
            'supports'   => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon'  => 'dashicons-tickets-alt',
            'taxonomies' => [
                'production_stage'    => PublicModel::taxonomy('Stages', 'Stage', self::STAGES, 'in_creation'),
                'production_language' => PublicModel::taxonomy('Languages', 'Language'),
                'production_audience' => PublicModel::taxonomy('Audiences', 'Audience'),
            ],
        ]);
    }

    private static function fields(): array
    {
        return [
            'premiere_year' => [
                'type'     => 'int',
                'required' => true,
                'validate' => static fn($year) => ((int) $year >= 1900 && (int) $year <= 2100) ?: 'premiere_year must be between 1900 and 2100',
            ],
            'kind'          => self::oneOf(self::KINDS),
            'min_age'       => ['type' => 'int'],
            'runtime'       => ['type' => 'int', 'description' => 'Minutes'],
            'trailer_url'   => ['type' => 'url'],
            'gallery'       => ['type' => 'gallery'],
            'press_quotes'  => [
                'type'       => 'repeater',
                'sub_fields' => [
                    'quote'  => ['type' => 'textarea', 'label' => 'Quote'],
                    'source' => ['type' => 'text', 'label' => 'Source'],
                    'date'   => ['type' => 'date', 'label' => 'Date'],
                ],
            ],
            'credit_concept'    => self::people('Concept'),
            'credit_direction'  => self::people('Direction'),
            'credit_performers' => self::people('Performers'),
            'credit_music'      => self::people('Music'),
            'credit_design'     => self::people('Design'),
            'other_credits'     => [
                'type'        => 'repeater',
                'description' => 'People without a page of their own',
                'sub_fields'  => [
                    'role' => ['type' => 'text', 'label' => 'Role'],
                    'name' => ['type' => 'text', 'label' => 'Name'],
                ],
            ],
            'coproducers' => ['type' => 'relation', 'post_type' => OrganisationModel::POST_TYPE, 'multiple' => true],
            'funders'     => ['type' => 'relation', 'post_type' => OrganisationModel::POST_TYPE, 'multiple' => true],
        ];
    }

    private static function people(string $role): array
    {
        return ['type' => 'relation', 'post_type' => PersonModel::POST_TYPE, 'multiple' => true, 'label' => $role];
    }

    // `select` sanitizes but does not validate: its options are a UI list,
    // and a value outside them would drop a production from every list.
    private static function oneOf(array $values): array
    {
        return [
            'type'     => 'select',
            'required' => true,
            'options'  => array_combine($values, $values),
            'validate' => static fn($value) => in_array($value, $values, true) ?: sprintf('must be one of: %s', implode(', ', $values)),
        ];
    }
}
