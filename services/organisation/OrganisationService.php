<?php

declare(strict_types=1);

namespace Atelier\Organisation;


defined('ABSPATH') || exit;

final class OrganisationService
{
    // No archive: a site's Partners page is a builder page on this slug.
    private const DEFAULTS = [
        'slug'    => 'organisations',
        'archive' => false,
        'labels'  => ['name' => 'Organisations', 'singular_name' => 'Organisation'],
    ];

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        OrganisationModel::register(apply_filters('ntdst/service/organisation/config', self::DEFAULTS));
    }
}
