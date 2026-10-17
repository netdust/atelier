<?php

declare(strict_types=1);

namespace Atelier\Grants;

use Atelier\Support\PrivateModel;

defined('ABSPATH') || exit;

/**
 * A personal link to one piece of gated content: its token, the address it was issued to, and what
 * it opens — an opaque `content_type` and `content_id`, never interpreted here. Possession of the
 * token is the access, so a record is a credential beside a person's address: no edit screen, no
 * export, protected meta only, and no field reaches the builder (PrivateFields).
 */
final class GrantModel
{
    public const POST_TYPE = 'access_grant';

    public const LOCKED = ['token', 'recipient_email', 'content_type', 'content_id', 'status', 'access_count', 'last_accessed', 'last_sent', 'lang'];
    public const PRIVATE = self::LOCKED;

    private const DEFAULTS = ['labels' => ['name' => 'Access grants', 'singular_name' => 'Access grant']];

    public static function register(array $config = self::DEFAULTS): void
    {
        PrivateModel::register(self::POST_TYPE, '_atelier_grant_', $config, self::fields(), self::LOCKED, [
            // A token is never typed or read on a screen of its own; the kit's edit screen lists its grants.
            'show_ui'           => false,
            'show_in_menu'      => false,
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => false,
            'query_var'         => false,
            'can_export'        => false,
            'auto_metabox'      => false,
            'supports'          => ['title'],
        ]);
    }

    private static function fields(): array
    {
        return [
            'token'           => ['type' => 'text'],
            'recipient_email' => ['type' => 'email'],
            'content_type'    => ['type' => 'text'],
            'content_id'      => ['type' => 'int'],
            // Only `active` admits; revoke() writes `revoked`.
            'status'          => ['type' => 'text'],
            'access_count'    => ['type' => 'int'],
            // UTC, `Y-m-d H:i:s`.
            'last_accessed'   => ['type' => 'text'],
            'last_sent'       => ['type' => 'text'],
            // The language the link was issued in (R-2): its mail and its URL.
            'lang'            => ['type' => 'text'],
        ];
    }
}
