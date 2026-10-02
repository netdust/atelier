<?php

/**
 * Required only once YOOtheme is known to be loaded (ProfileService::init()).
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace Atelier\Profile;

defined('ABSPATH') || exit;

/**
 * @param array<string, mixed> $config The resolved profile service config.
 */
function register_profile_source(array $config): void
{
    \YOOtheme\Event::on('source.init', static function ($source) use ($config): void {
        $source->queryType(['fields' => ProfileSources::queries($config)]);
        // Merges with baseline's CompanyProfile, which carries the declared fields.
        $source->objectType('CompanyProfile', ProfileSources::type());
    }, -10);
}
