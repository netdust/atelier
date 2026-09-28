<?php

declare(strict_types=1);

namespace Atelier\Organisation;

use NTDST_Service_Meta;

defined('ABSPATH') || exit;

final class OrganisationService implements NTDST_Service_Meta
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

    public static function metadata(): array
    {
        return [
            'description' => 'Organisations: venues, co-producers, funders and presenters in one record.',
            'priority'    => 10,
        ];
    }

    private function init(): void
    {
        OrganisationModel::register(apply_filters('ntdst/service/organisation/config', self::DEFAULTS));
    }
}
