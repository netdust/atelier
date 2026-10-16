<?php

/**
 * Required only once YOOtheme is known to be loaded (PressKitService::init()).
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace Atelier\PressKit;

use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Config;
use YOOtheme\Event;

use function YOOtheme\app;

defined('ABSPATH') || exit;

/**
 * The kit's template type in the customizer, the view that chooses it, and the `currentPressKit` source.
 *
 * @param array<string, mixed> $config The resolved presskit service config.
 */
function register_presskit_template(array $config): void
{
    // -10, as YOOtheme's ACF and Toolset integrations: after its own types, before -20 lists them.
    Event::on(BuilderConfig::class, static function (BuilderConfig $builder) use ($config): void {
        $template = ['label' => (string) ($config['labels']['name'] ?? 'Press kit')];
        // YOOtheme's own language limit, as its search template always carries it.
        $locale = $builder->get('templates.search.fieldset.default.fields.locale');

        if (is_array($locale)) {
            $template['fieldset'] = ['default' => ['fields' => ['locale' => $locale]]];
        }

        $builder->merge(['templates' => [PressKitSources::TEMPLATE => $template]]);
    }, -10);

    // -10 runs after YOOtheme's MatchTemplate: on a kit URL it saw the blog index, and this replaces it.
    Event::on(
        'builder.template',
        static fn($query): ?array => PressKitSources::view($query, (string) app(Config::class)->get('locale.code')),
        -10,
    );

    Event::on('source.init', static function ($source) use ($config): void {
        $source->queryType(['fields' => PressKitSources::queries($config)]);

        foreach (PressKitSources::types() as $name => $type) {
            $source->objectType($name, $type);
        }
    }, -10);
}
