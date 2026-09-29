<?php

declare(strict_types=1);

namespace Atelier\Models;

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

    /**
     * Published organisations in `$country`. Trimmed on read: a meta-query would compare the
     * caller's trimmed value against a raw, possibly padded, stored one.
     *
     * @return list<int>
     */
    public function idsByCountry(string $country): array
    {
        $ids = [];

        foreach (ntdst_data()->get(OrganisationModel::POST_TYPE)->withMeta()->limit(-1)->get() as $row) {
            if (trim((string) ($row['meta']['country'] ?? '')) === $country) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        }

        return $ids;
    }
}
