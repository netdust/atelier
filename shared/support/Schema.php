<?php

declare(strict_types=1);

namespace Atelier\Support;

use WP_Post;

defined('ABSPATH') || exit;

/** The JSON-LD pieces more than one atelier model builds. */
final class Schema
{
    public static function description(WP_Post $post): string
    {
        $text = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;

        return wp_trim_words(wp_strip_all_tags($text), 40);
    }

    /** @return array{'@type': string, name: string, url: string}|null */
    public static function personRef(int $id): ?array
    {
        $person = get_post($id);
        if (!$person instanceof WP_Post || $person->post_status !== 'publish') {
            return null;
        }

        return ['@type' => 'Person', 'name' => get_the_title($person), 'url' => (string) get_permalink($person)];
    }
}
