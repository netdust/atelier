<?php

declare(strict_types=1);

namespace Atelier\Person;

use NTDST_Service_Meta;

defined('ABSPATH') || exit;

final class PersonService implements NTDST_Service_Meta
{
    private const DEFAULTS = [
        'slug'    => 'people',
        'archive' => false,
        'labels'  => ['name' => 'People', 'singular_name' => 'Person'],
    ];

    public function __construct()
    {
        $this->init();
    }

    public static function metadata(): array
    {
        return [
            'description' => 'People: a page per person, with credits read back from productions.',
            'priority'    => 10,
        ];
    }

    private function init(): void
    {
        PersonModel::register(apply_filters('ntdst/service/person/config', self::DEFAULTS));
    }
}
