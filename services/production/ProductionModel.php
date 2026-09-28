<?php

declare(strict_types=1);

namespace Atelier\Production;

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
     * ntdst/production/fields; every `credit_` relation to a person is a role.
     */
    public const ROLE_PREFIX = 'credit_';

    public static function register(array $config): void
    {
        PublicModel::register(self::POST_TYPE, '_atelier_production_', $config, self::fields(), self::LOCKED, [
            'supports'   => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon'  => 'dashicons-tickets-alt',
            'taxonomies' => [
                'production_stage'    => PublicModel::taxonomy('Stages', 'Stage', self::STAGES),
                'production_language' => PublicModel::taxonomy('Languages', 'Language'),
                'production_audience' => PublicModel::taxonomy('Audiences', 'Audience'),
            ],
        ]);
    }

    /**
     * The credit roles as registered — after site filters, so a role a site
     * adds counts. Key => label.
     *
     * @return array<string, string>
     */
    public static function roleFields(): array
    {
        $roles = [];

        foreach (ntdst_data()->get(self::POST_TYPE)->getSchema() as $key => $field) {
            if (
                str_starts_with((string) $key, self::ROLE_PREFIX)
                && ($field['type'] ?? '') === 'relation'
                && ($field['post_type'] ?? '') === 'person'
            ) {
                $roles[$key] = $field['label'] ?? ucwords(str_replace('_', ' ', substr($key, strlen(self::ROLE_PREFIX))));
            }
        }

        return $roles;
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
            'coproducers' => ['type' => 'relation', 'post_type' => 'organisation', 'multiple' => true],
            'funders'     => ['type' => 'relation', 'post_type' => 'organisation', 'multiple' => true],
        ];
    }

    private static function people(string $role): array
    {
        return ['type' => 'relation', 'post_type' => 'person', 'multiple' => true, 'label' => $role];
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
