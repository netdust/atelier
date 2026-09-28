<?php

declare(strict_types=1);

namespace Atelier\Support;

defined('ABSPATH') || exit;

/**
 * A site that overrides a model's slug needs its rewrite rules rebuilt once,
 * not on every request and not by a manual Permalinks save.
 */
final class RewriteGuard
{
    private const OPTION = 'atelier_rewrite_slugs';

    /** @var array<string, string> */
    private static array $slugs = [];

    private static bool $hooked = false;

    public static function track(string $postType, string $slug, string $archive): void
    {
        self::$slugs[$postType] = $slug . '|' . $archive;

        if (self::$hooked) {
            return;
        }

        self::$hooked = true;
        add_action('init', [self::class, 'flushWhenChanged'], 99);
    }

    public static function flushWhenChanged(): void
    {
        $slugs = self::$slugs;
        ksort($slugs);
        $hash = md5((string) wp_json_encode($slugs));

        if (get_option(self::OPTION) === $hash) {
            return;
        }

        flush_rewrite_rules(false);
        update_option(self::OPTION, $hash);
    }
}
