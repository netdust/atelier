<?php

declare(strict_types=1);

namespace Atelier\Production;

use NTDST_Service_Meta;

defined('ABSPATH') || exit;

final class ProductionService implements NTDST_Service_Meta
{
    private const DEFAULTS = [
        'slug'    => 'productions',
        'archive' => 'productions',
        'labels'  => ['name' => 'Productions', 'singular_name' => 'Production'],
    ];

    public function __construct()
    {
        $this->init();
    }

    public static function metadata(): array
    {
        return [
            'description' => 'Productions: the creative work a company tours, with credits, media and press.',
            'priority'    => 10,
        ];
    }

    /**
     * The credit roles as registered — after site filters, so a role Laika
     * adds counts. Key => label.
     *
     * @return array<string, string>
     */
    public function roleFields(): array
    {
        $roles = [];

        foreach (ntdst_data()->get(ProductionModel::POST_TYPE)->getSchema() as $key => $field) {
            if (
                str_starts_with((string) $key, ProductionModel::ROLE_PREFIX)
                && ($field['type'] ?? '') === 'relation'
                && ($field['post_type'] ?? '') === 'person'
            ) {
                $roles[$key] = $field['label'] ?? ucwords(str_replace('_', ' ', substr($key, strlen(ProductionModel::ROLE_PREFIX))));
            }
        }

        return $roles;
    }

    private function init(): void
    {
        ProductionModel::register(apply_filters('ntdst/service/production/config', self::DEFAULTS));
    }
}
