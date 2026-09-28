<?php

declare(strict_types=1);

namespace Atelier\Production;

use NTDST_Data_Model;
use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * The one place `production` data is read or written.
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
     * What tours now or is being made, newest premiere first.
     *
     * @return list<WP_Post>
     */
    public function touring(): array
    {
        $rows = ntdst_data()->get(ProductionModel::POST_TYPE)
            ->where('post_status', 'publish')
            ->whereIn('stage', ['touring', 'in_creation'])
            ->withMeta()
            ->get();

        usort($rows, static fn(array $a, array $b): int => self::byYear($a, $b) ?: strcmp($a['title'], $b['title']));

        return self::posts($rows);
    }

    /**
     * Every published production, newest premiere first; within a year a
     * premiere before a rerun before an "and also".
     *
     * @return list<WP_Post>
     */
    public function previously(): array
    {
        $rows = ntdst_data()->get(ProductionModel::POST_TYPE)
            ->where('post_status', 'publish')
            ->withMeta()
            ->get();

        $kind = static fn(array $row): int => (int) array_search($row['meta']['kind'] ?? '', ProductionModel::KINDS, true);
        usort($rows, static fn(array $a, array $b): int => self::byYear($a, $b)
            ?: ($kind($a) <=> $kind($b))
            ?: strcmp($a['title'], $b['title']));

        return self::posts($rows);
    }

    /**
     * The published productions that credit this person, newest premiere
     * first, each with the role labels the person holds on it. `$role`
     * narrows to one role field; a field that is not a role answers nothing.
     *
     * @return list<array{production: WP_Post, roles: list<string>}>
     */
    public function creditedTo(int $personId, ?string $role = null): array
    {
        $roles = ProductionModel::roleFields();

        if ($role !== null) {
            if (!isset($roles[$role])) {
                return [];
            }
            $roles = [$role => $roles[$role]];
        }

        $rows = ntdst_data()->get(ProductionModel::POST_TYPE)
            ->where('post_status', 'publish')
            ->whereGroup('OR', static function (NTDST_Data_Model $q) use ($roles, $personId): void {
                foreach (array_keys($roles) as $field) {
                    $q->whereRelated($field, $personId);
                }
            })
            ->withMeta()
            ->get();

        usort($rows, [self::class, 'byYear']);

        $credits = [];
        foreach ($rows as $row) {
            $post = get_post((int) $row['id']);
            if (!$post instanceof WP_Post) {
                continue;
            }

            $held = [];
            foreach ($roles as $field => $label) {
                if (in_array($personId, array_map('intval', (array) ($row['meta'][$field] ?? [])), true)) {
                    $held[] = $label;
                }
            }

            $credits[] = ['production' => $post, 'roles' => $held];
        }

        return $credits;
    }

    private static function byYear(array $a, array $b): int
    {
        return ($b['meta']['premiere_year'] ?? 0) <=> ($a['meta']['premiere_year'] ?? 0);
    }

    /**
     * YOOtheme's types resolve from WP_Post; the chain's get() returns arrays.
     *
     * @return list<WP_Post>
     */
    private static function posts(array $rows): array
    {
        return array_values(array_filter(
            array_map(static fn(array $row): ?WP_Post => get_post((int) $row['id']), $rows),
        ));
    }
}
