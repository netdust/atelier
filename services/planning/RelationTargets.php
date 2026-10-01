<?php

declare(strict_types=1);

namespace Atelier\Planning;

use WP_Error;

defined('ABSPATH') || exit;

/**
 * Core's relation sanitizer stores any positive id; a planning relation
 * holds only records of its own type. The repositories ask here before a write.
 */
final class RelationTargets
{
    /**
     * @param array<string, mixed> $data the write, relations normalised to lists
     * @param array<string, array{string, string}> $targets field => [post type, refusal code]
     */
    public static function refuse(array $data, array $targets): ?WP_Error
    {
        foreach ($targets as $field => [$type, $code]) {
            foreach ((array) ($data[$field] ?? []) as $id) {
                // Core stores absint() of each item, so -5 is judged as the 5 it becomes.
                $stored = absint($id);
                if ($stored !== 0 && get_post_type($stored) !== $type) {
                    return new WP_Error($code, sprintf('The %s is not a %s.', $field, $type));
                }
            }
        }

        return null;
    }
}
