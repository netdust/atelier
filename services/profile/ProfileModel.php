<?php

declare(strict_types=1);

namespace Atelier\Profile;

use Atelier\Support\DownloadCount;
use Atelier\Support\PrivateModel;

defined('ABSPATH') || exit;

/**
 * The company profile: one record per site, its title the company name.
 * Private — a wp-admin screen reached only through the service's menu entry.
 * Text (tagline, bios, kit text) translates; every other value, repeater
 * cells included, is a fact the copies take from the Dutch record.
 */
final class ProfileModel
{
    public const POST_TYPE = 'company_profile';
    public const META_PREFIX = '_atelier_profile_';

    /** The social links, in the order the tab shows them and the schema lists them. */
    public const SOCIAL = ['website', 'instagram', 'facebook', 'youtube', 'vimeo', 'linkedin'];

    /** @param array{labels: array, groups?: array<string, string>} $config the service config after site overrides */
    public static function register(array $config): void
    {
        PrivateModel::register(self::POST_TYPE, self::META_PREFIX, $config, self::fields(), [], [
            'show_in_menu'  => false,
            'supports'      => ['title'],
            'use_tabs'      => true,
            'tabs_context'  => 'normal',
            'tabs_priority' => 'high',
            'metabox_title' => $config['labels']['singular_name'] ?? $config['labels']['name'],
            // A field in no group has no input, and an admin save clears it.
            'field_groups'  => self::groups($config['groups'] ?? []),
        ]);
    }

    /**
     * The tabs; a site names them through `groups` (key => title), English otherwise.
     *
     * @param array<string, string> $titles
     */
    private static function groups(array $titles): array
    {
        $groups = [];

        foreach (self::GROUPS as $key => [$title, $fields]) {
            $groups[$key] = ['title' => (string) ($titles[$key] ?? $title), 'fields' => $fields];
        }

        return $groups;
    }

    private const GROUPS = [
        'basic'   => ['Basic', ['legal_name', 'tagline', 'bio_short', 'bio_long']],
        'contact' => ['Contact', ['email', 'address_street', 'address_postcode', 'address_city', 'address_country', 'contacts']],
        'images'  => ['Images', ['logo', 'press_photo', 'press_photo_credit']],
        'social'  => ['Social', self::SOCIAL],
        'kit'     => ['Press kit', ['kit_public', 'kit_text', 'kit_photos', 'kit_documents', 'kit_download_count']],
    ];

    private static function fields(): array
    {
        $fact = static fn(string $type, array $more = []): array => ['type' => $type, 'translate' => false] + $more;

        return [
            'legal_name' => $fact('text'),
            'tagline'    => ['type' => 'text'],
            'bio_short'  => ['type' => 'textarea'],
            'bio_long'   => ['type' => 'html'],

            // The company's address for the public schema; a contact row's email names a person.
            'email'            => $fact('email'),
            'address_street'   => $fact('text'),
            'address_postcode' => $fact('text'),
            'address_city'     => $fact('text'),
            'address_country'  => $fact('text'),
            'contacts'         => [
                'type'       => 'repeater',
                'sub_fields' => [
                    'role'  => $fact('text', ['label' => 'Role']),
                    'name'  => $fact('text', ['label' => 'Name']),
                    'email' => $fact('email', ['label' => 'Email']),
                    'phone' => $fact('text', ['label' => 'Phone']),
                ],
            ],

            'logo'               => ['type' => 'image'],
            'press_photo'        => ['type' => 'image'],
            'press_photo_credit' => $fact('text'),

            ...array_fill_keys(self::SOCIAL, $fact('url')),

            'kit_public'    => ['type' => 'bool'],
            'kit_text'      => ['type' => 'html'],
            'kit_photos'    => [
                'type'       => 'repeater',
                'sub_fields' => [
                    'image'  => $fact('image', ['label' => 'Image']),
                    'credit' => $fact('text', ['label' => 'Credit']),
                ],
            ],
            'kit_documents' => [
                'type'       => 'repeater',
                'sub_fields' => [
                    'name' => $fact('text', ['label' => 'Name']),
                    'file' => $fact('file', ['label' => 'File']),
                ],
            ],
            'kit_download_count' => DownloadCount::field(),
        ];
    }
}
