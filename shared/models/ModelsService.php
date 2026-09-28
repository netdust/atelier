<?php

declare(strict_types=1);

namespace Atelier\Models;

use ntdst\baseline\services\SeoService;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * The records every atelier feature points at: people and organisations.
 * Productions credit them now; performances (venues) and Backstage (crew)
 * will too, so they belong to no single feature.
 */
final class ModelsService
{
    private const DEFAULTS = [
        'person' => [
            'slug'    => 'people',
            'archive' => false,
            'labels'  => ['name' => 'People', 'singular_name' => 'Person'],
        ],
        // No archive: a site's Partners page is a builder page on this slug.
        'organisation' => [
            'slug'    => 'organisations',
            'archive' => false,
            'labels'  => ['name' => 'Organisations', 'singular_name' => 'Organisation'],
        ],
    ];

    public function __construct()
    {
        $this->init();
    }

    /**
     * A person's JSON-LD: public facts only — PersonModel::PRIVATE never
     * reaches it.
     */
    public function personSchema(?array $schema, WP_Post $post): array
    {
        return array_filter([
            '@context'    => 'https://schema.org',
            '@type'       => 'Person',
            'name'        => get_the_title($post),
            'description' => SeoService::describePost($post),
            'url'         => get_permalink($post),
            'image'       => get_the_post_thumbnail_url($post, 'large') ?: null,
        ], static fn($value): bool => $value !== null && $value !== '');
    }

    private function init(): void
    {
        add_filter('ntdst/baseline/schema/post_type/person', [$this, 'personSchema'], 10, 2);

        $config = apply_filters('ntdst/service/models/config', self::DEFAULTS);
        PersonModel::register($config['person']);
        OrganisationModel::register($config['organisation']);
    }
}
