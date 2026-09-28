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
