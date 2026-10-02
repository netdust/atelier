<?php

declare(strict_types=1);

namespace Atelier\Models;

use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * The one place `person` data is read or written.
 */
final class PersonRepository
{
    public function find(int $id, string $status = 'publish'): WP_Post|WP_Error
    {
        return ntdst_data()->get(PersonModel::POST_TYPE)->find($id, $status);
    }

    public function create(array $data): WP_Post|WP_Error
    {
        return ntdst_data()->get(PersonModel::POST_TYPE)->create($data);
    }

    public function update(int $id, array $data): WP_Post|WP_Error
    {
        return ntdst_data()->get(PersonModel::POST_TYPE)->update($id, $data);
    }
}
