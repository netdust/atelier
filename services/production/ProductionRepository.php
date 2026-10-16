<?php

declare(strict_types=1);

namespace Atelier\Production;

use Atelier\Models\PersonModel;
use Atelier\Support\Languages;
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

    /** One more download of the kit on `$id`: the count is a callback field, read back as text, so it is cast. */
    public function countDownload(int $id): true|WP_Error
    {
        $model = ntdst_data()->get(ProductionModel::POST_TYPE);

        return $model->updateMeta($id, 'kit_download_count', absint($model->getMeta($id, 'kit_download_count')) + 1);
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

    /** Published only, in Polylang's `$lang`: a copy may carry its Dutch record's slug. */
    public function findPublishedBySlugIn(string $slug, string $lang): ?WP_Post
    {
        if ($slug === '' || $lang === '') {
            return null;
        }

        $holders = ntdst_data()->get(ProductionModel::POST_TYPE)->where('post_name', $slug)->where('post_status', 'publish')->all();
        $id = Languages::holderIn($holders, $lang);
        $post = $id === null ? null : $this->find($id);

        return $post instanceof WP_Post ? $post : null;
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
