<?php

declare(strict_types=1);

namespace Atelier\Support;

use Atelier\Models\PersonRepository;
use WP_Post;

defined('ABSPATH') || exit;

/** The JSON-LD pieces more than one atelier model builds. */
final class Schema
{
    /** @return array{'@type': string, name: string, url: string}|null */
    public static function personRef(int $id): ?array
    {
        // find() is publish-only: a draft or missing person is no contributor.
        $person = ntdst_get(PersonRepository::class)->find($id);
        if (!$person instanceof WP_Post) {
            return null;
        }

        return ['@type' => 'Person', 'name' => get_the_title($person), 'url' => (string) get_permalink($person)];
    }
}
