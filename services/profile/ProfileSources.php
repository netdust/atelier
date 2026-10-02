<?php

declare(strict_types=1);

namespace Atelier\Profile;

use WP_Post;

defined('ABSPATH') || exit;

/**
 * The `companyProfile` query and the `name` YOOtheme cannot build for a private
 * post type; baseline publishes the declared fields onto the same `CompanyProfile`.
 * Loaded without YOOtheme so the integration tier can call it.
 */
final class ProfileSources
{
    /**
     * Labelled with the site's own word for the model, never atelier's.
     *
     * @param array<string, mixed> $config The resolved profile service config.
     * @return array<string, array<string, mixed>>
     */
    public static function queries(array $config): array
    {
        $label = (string) ($config['labels']['singular_name'] ?? $config['labels']['name'] ?? 'Profile');

        return [
            'companyProfile' => [
                'type'       => 'CompanyProfile',
                'metadata'   => ['label' => $label, 'group' => $label],
                'extensions' => ['call' => self::class . '::resolve'],
            ],
        ];
    }

    /**
     * `type => true`: with no YOOtheme base type, this is what makes the picker list the fields.
     *
     * @return array<string, mixed>
     */
    public static function type(): array
    {
        return [
            'fields'   => [
                'name' => [
                    'type'       => 'String',
                    'metadata'   => ['label' => 'Name'],
                    'extensions' => ['call' => self::class . '::name'],
                ],
            ],
            'metadata' => ['type' => true],
        ];
    }

    /** The published profile in the page's language, as the post baseline's field resolvers read. */
    public static function resolve(mixed $root): ?WP_Post
    {
        $profile = ntdst_get(ProfileRepository::class)->current();

        return $profile === null ? null : get_post($profile['id']);
    }

    public static function name(WP_Post $profile): string
    {
        return $profile->post_title;
    }
}
