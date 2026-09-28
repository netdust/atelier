<?php

declare(strict_types=1);

namespace Atelier\Production;

use Atelier\Support\Labels;

defined('ABSPATH') || exit;

final class ProductionModel
{
    public const POST_TYPE = 'production';
    public const STAGES = ['in_creation', 'touring', 'archive'];
    public const KINDS = ['premiere', 'rerun', 'also'];

    public static function register(array $config): void
    {
        ntdst_data()->register(self::POST_TYPE, [
            'meta_prefix'        => '_atelier_production_',
            // core registers the WordPress post type only when `label` is set
            'label'              => $config['labels']['name'],
            'labels'             => Labels::expand($config['labels']),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_rest'       => false,
            'has_archive'        => $config['archive'],
            'rewrite'            => ['slug' => $config['slug'], 'with_front' => false],
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon'          => 'dashicons-tickets-alt',
            'fields'             => self::fields(),
            'taxonomies'         => [
                'production_language' => self::taxonomy('Languages', 'Language'),
                'production_audience' => self::taxonomy('Audiences', 'Audience'),
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
            'stage'         => self::oneOf(self::STAGES),
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
        ];
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

    private static function taxonomy(string $plural, string $singular): array
    {
        return [
            'labels'             => ['name' => $plural, 'singular_name' => $singular],
            'publicly_queryable' => false,
            'rewrite'            => false,
            'show_in_rest'       => false,
            'show_admin_column'  => true,
        ];
    }
}
