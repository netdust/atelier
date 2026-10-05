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

    /**
     * Published, in the default language when Polylang translates people: their
     * copies carry the same slug. A slug query is singular, and WordPress drops a
     * singular query's tax_query, so each holder's language is read instead.
     */
    public function findBySlug(string $slug): ?WP_Post
    {
        $holders = ntdst_data()->get(PersonModel::POST_TYPE)->where('post_name', $slug)->where('post_status', 'publish')->all();
        $lang = function_exists('pll_is_translated_post_type') && pll_is_translated_post_type(PersonModel::POST_TYPE) ? pll_default_language() : null;

        foreach ($holders as $row) {
            if ($lang === null || pll_get_post_language($row['id']) === $lang) {
                $post = $this->find($row['id']);

                return $post instanceof WP_Post ? $post : null;
            }
        }

        return null;
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
