<?php

declare(strict_types=1);

namespace Atelier\Person;

use Atelier\Support\Schema;
use WP_Post;

defined('ABSPATH') || exit;

final class PersonService
{
    private const DEFAULTS = [
        'slug'    => 'people',
        'archive' => false,
        'labels'  => ['name' => 'People', 'singular_name' => 'Person'],
    ];

    public function __construct()
    {
        $this->init();
    }

    /**
     * The person's JSON-LD: public facts only. Email, phone and the rest of
     * PersonModel::PRIVATE never reach it.
     */
    public function schema(?array $schema, WP_Post $post): array
    {
        return array_filter([
            '@context'    => 'https://schema.org',
            '@type'       => 'Person',
            'name'        => get_the_title($post),
            'description' => Schema::description($post),
            'url'         => get_permalink($post),
            'image'       => get_the_post_thumbnail_url($post, 'large') ?: null,
        ], static fn($value): bool => $value !== null && $value !== '');
    }

    private function init(): void
    {
        add_filter('ntdst/baseline/schema/post_type/person', [$this, 'schema'], 10, 2);

        PersonModel::register(apply_filters('ntdst/service/person/config', self::DEFAULTS));
    }
}
