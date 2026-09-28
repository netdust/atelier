<?php

declare(strict_types=1);

namespace Atelier\Organisation;

use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * The one place `organisation` data is read or written.
 */
final class OrganisationRepository
{
    public function find(int $id): WP_Post|WP_Error
    {
        return ntdst_data()->get(OrganisationModel::POST_TYPE)->find($id);
    }

    public function create(array $data): WP_Post|WP_Error
    {
        return ntdst_data()->get(OrganisationModel::POST_TYPE)->create($data);
    }

    public function update(int $id, array $data): WP_Post|WP_Error
    {
        return ntdst_data()->get(OrganisationModel::POST_TYPE)->update($id, $data);
    }
}
