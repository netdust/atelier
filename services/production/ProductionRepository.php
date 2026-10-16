<?php

declare(strict_types=1);

namespace Atelier\Production;

use Atelier\Models\PersonModel;
use NTDST_FieldTypes;
use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * The one place `production` data is read or written. Lists are the
 * builder's: stage is a taxonomy and credits walk back through baseline.
 */
final class ProductionRepository
{
    public function find(int $id): WP_Post|WP_Error
    {
        return ntdst_data()->get(ProductionModel::POST_TYPE)->find($id);
    }

    public function create(array $data): WP_Post|WP_Error
    {
        return ntdst_data()->get(ProductionModel::POST_TYPE)->create($data);
    }

    public function update(int $id, array $data): WP_Post|WP_Error
    {
        return ntdst_data()->get(ProductionModel::POST_TYPE)->update($id, $data);
    }

    /**
     * Published only. An empty slug is refused outright: WP_Query ignores an empty `name` and would
     * return the first published row.
     */
    public function findPublishedBySlug(string $slug): ?WP_Post
    {
        if ($slug === '') {
            return null;
        }

        return ntdst_data()->get(ProductionModel::POST_TYPE)->where('post_name', $slug)->first();
    }

    /**
     * Published only, in Polylang's `$lang`: a copy may carry its Dutch record's slug. Checked per row —
     * `name` makes the query singular, and WP_Query drops a singular query's tax_query.
     */
    public function findPublishedBySlugIn(string $slug, string $lang): ?WP_Post
    {
        if ($slug === '' || $lang === '') {
            return null;
        }

        foreach (ntdst_data()->get(ProductionModel::POST_TYPE)->where('post_name', $slug)->get() as $row) {
            if (pll_get_post_language((int) $row['id']) === $lang) {
                $post = $this->find((int) $row['id']);

                return $post instanceof WP_Post ? $post : null;
            }
        }

        return null;
    }

    /**
     * The credit roles as registered — after site filters, so a role a site
     * adds counts. Key => label.
     *
     * @return array<string, string>
     */
    public function roleFields(): array
    {
        $roles = [];

        foreach (ntdst_data()->get(ProductionModel::POST_TYPE)->getSchema() as $key => $field) {
            if (
                str_starts_with((string) $key, ProductionModel::ROLE_PREFIX)
                && NTDST_FieldTypes::declaredType($field) === 'relation'
                && ($field['post_type'] ?? '') === PersonModel::POST_TYPE
            ) {
                $roles[$key] = $field['label'] ?? ucwords(str_replace('_', ' ', substr($key, strlen(ProductionModel::ROLE_PREFIX))));
            }
        }

        return $roles;
    }
}
