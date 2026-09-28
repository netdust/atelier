<?php

declare(strict_types=1);

namespace Atelier\Support;

use WP_Post;

defined('ABSPATH') || exit;

/**
 * A taxonomy meta box with radio buttons: for a vocabulary where a post holds
 * exactly one term (a production's stage). WordPress ships only checkboxes.
 */
final class SingleTermBox
{
    /** @param array{args?: array{taxonomy?: string}} $box */
    public static function render(WP_Post $post, array $box): void
    {
        $taxonomy = (string) ($box['args']['taxonomy'] ?? '');
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        if (!is_array($terms)) {
            return;
        }

        $current = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids']);
        $current = is_array($current) ? array_map('intval', $current) : [];

        foreach ($terms as $term) {
            printf(
                '<label style="display:block"><input type="radio" name="tax_input[%1$s][]" value="%2$d"%3$s> %4$s</label>',
                esc_attr($taxonomy),
                (int) $term->term_id,
                checked(in_array((int) $term->term_id, $current, true), true, false),
                esc_html($term->name),
            );
        }
    }
}
