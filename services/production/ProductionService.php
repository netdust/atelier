<?php

declare(strict_types=1);

namespace Atelier\Production;

use Atelier\Support\Schema;
use ntdst\baseline\services\SeoService;
use WP_Post;

defined('ABSPATH') || exit;

final class ProductionService
{
    private const DEFAULTS = [
        'slug'    => 'productions',
        'archive' => 'productions',
        'labels'  => ['name' => 'Productions', 'singular_name' => 'Production'],
    ];

    public function __construct(private readonly ProductionRepository $repository)
    {
        $this->init();
    }

    /**
     * The production's JSON-LD for baseline's schema module: a CreativeWork
     * whose contributors are the credited people, each listed once.
     */
    public function schema(?array $schema, WP_Post $post): array
    {
        $production = $this->repository->find($post->ID);
        $fields = is_wp_error($production) ? [] : $production->fields;

        $people = [];
        foreach (array_keys($this->repository->roleFields()) as $role) {
            foreach ((array) ($fields[$role] ?? []) as $id) {
                $people[(int) $id] = true;
            }
        }

        return array_filter([
            '@context'        => 'https://schema.org',
            '@type'           => 'CreativeWork',
            'name'            => get_the_title($post),
            'description'     => SeoService::describePost($post),
            'url'             => get_permalink($post),
            'image'           => get_the_post_thumbnail_url($post, 'large') ?: null,
            'dateCreated'     => empty($fields['premiere_year']) ? null : (string) $fields['premiere_year'],
            'typicalAgeRange' => empty($fields['min_age']) ? null : $fields['min_age'] . '+',
            'timeRequired'    => empty($fields['runtime']) ? null : 'PT' . (int) $fields['runtime'] . 'M',
            'contributor'     => array_values(array_filter(array_map([Schema::class, 'personRef'], array_keys($people)))),
        ], static fn($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private function init(): void
    {
        add_filter('ntdst/baseline/schema/post_type/production', [$this, 'schema'], 10, 2);

        ProductionModel::register(apply_filters('ntdst/service/production/config', self::DEFAULTS));
    }
}
