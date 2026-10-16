<?php

declare(strict_types=1);

namespace Atelier\PressKit;

use WP_Post;
use WP_Query;

defined('ABSPATH') || exit;

/**
 * The kit template's view decision and its `currentPressKit` query. Loaded without YOOtheme so the
 * integration tier can call it; yootheme.php only wires it.
 */
final class PressKitSources
{
    public const TEMPLATE = 'atelier-presskit';

    /**
     * YOOtheme's view for a kit URL: the kit template when the kit opens, the error-404 template when it
     * is refused — YOOtheme matches a view on type and locale only, so a refusal is the page every
     * unknown URL gets. A download never renders a page: it streams its zip, or it is that 404. null on
     * any other URL, leaving YOOtheme's own match. `$locale` is YOOtheme's locale code, split as its
     * MatchTemplate splits it.
     *
     * @return array{type: string, query: array{locale: string|list<string>}}|null
     */
    public static function view(WP_Query $query, ?string $locale = null): ?array
    {
        $decision = ntdst_get(PressKitService::class)->decision($query);

        if ($decision === null) {
            return null;
        }

        $locale ??= determine_locale();

        return [
            'type'  => $decision['admitted'] && !str_ends_with($decision['route'], '_download') ? self::TEMPLATE : 'error-404',
            'query' => ['locale' => str_contains($locale, '_') ? [$locale, (string) strstr($locale, '_', true)] : $locale],
        ];
    }

    /**
     * Labelled with the site's own word for a kit, and offered in the kit template only.
     *
     * @param array<string, mixed> $config The resolved presskit service config.
     * @return array<string, array<string, mixed>>
     */
    public static function queries(array $config): array
    {
        $label = (string) ($config['labels']['name'] ?? 'Press kit');

        return [
            'currentPressKit' => [
                'type'       => 'PressKit',
                'metadata'   => ['label' => $label, 'group' => $label, 'view' => [self::TEMPLATE]],
                'extensions' => ['call' => self::class . '::resolve'],
            ],
        ];
    }

    /**
     * `type => true` on each: with no YOOtheme base type, that is what makes the picker list the fields.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function types(): array
    {
        $string = static fn(string $label): array => ['type' => 'String', 'metadata' => ['label' => $label]];
        $list = static fn(string $type, string $label): array => ['type' => ['listOf' => $type], 'metadata' => ['label' => $label]];

        return [
            'PressKit'      => [
                'fields'   => [
                    'title'      => $string('Title'),
                    'text'       => $string('Text'),
                    'photos'     => $list('PressKitPhoto', 'Photos'),
                    'logos'      => $list('PressKitFile', 'Logos'),
                    'documents'  => $list('PressKitFile', 'Documents'),
                    // YOOtheme's own type: the layout binds the production's facts as single-production does.
                    'production' => [
                        'type'       => 'Production',
                        'metadata'   => ['label' => 'Production'],
                        'extensions' => ['call' => self::class . '::production'],
                    ],
                ],
                'metadata' => ['type' => true],
            ],
            'PressKitPhoto' => ['fields' => ['url' => $string('URL'), 'credit' => $string('Credit')], 'metadata' => ['type' => true]],
            'PressKitFile'  => ['fields' => ['name' => $string('Name'), 'url' => $string('URL')], 'metadata' => ['type' => true]],
        ];
    }

    /**
     * The kit this request opened, each value escaped for the HTML it lands in; null on a refusal.
     *
     * @return array<string, mixed>|null
     */
    public static function resolve(mixed $root): ?array
    {
        $decision = ntdst_get(PressKitService::class)->decision();

        if ($decision === null || !$decision['admitted']) {
            return null;
        }

        $kit = $decision['kit'];
        $named = static fn(array $file): array => ['name' => esc_html($file['name']), 'url' => esc_url($file['url'])];

        return [
            'type'      => $kit['type'],
            'id'        => $kit['id'],
            'title'     => esc_html($kit['title']),
            'text'      => wp_kses_post($kit['text']),
            'photos'    => array_map(static fn(array $photo): array => ['url' => esc_url($photo['url']), 'credit' => esc_html($photo['credit'])], $kit['photos']),
            'logos'     => array_map($named, $kit['logos']),
            'documents' => array_map($named, $kit['documents']),
        ];
    }

    /**
     * The production in the kit's language, as YOOtheme's `Production` fields read it. None on the
     * company's kit: the template's production facts render empty there, and YOOtheme drops them.
     */
    public static function production(array $kit): ?WP_Post
    {
        return $kit['type'] === 'production' ? get_post((int) $kit['id']) : null;
    }
}
