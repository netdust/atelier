<?php

declare(strict_types=1);

namespace Atelier\Models;

use Atelier\Support\PublicModel;

defined('ABSPATH') || exit;

/**
 * A person with a page: the bio is the editor, the photo the thumbnail.
 * Credits are not stored here — they are read back from productions.
 */
final class PersonModel
{
    public const POST_TYPE = 'person';

    /** Never in REST, never in the builder. */
    public const PRIVATE = ['email', 'phone', 'wp_user', 'dietary'];

    public static function register(array $config): void
    {
        PublicModel::register(self::POST_TYPE, '_atelier_person_', $config, self::fields(), [], [
            'supports'  => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-groups',
        ]);
    }

    private static function fields(): array
    {
        return [
            'core_team' => ['type' => 'bool'],
            'email'     => ['type' => 'email'],
            'phone'     => ['type' => 'text'],
            'wp_user'   => ['type' => 'int'],
            'dietary'   => ['type' => 'textarea'],
        ];
    }
}
