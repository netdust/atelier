<?php

declare(strict_types=1);

namespace Atelier\Planning;

use Atelier\Models\PersonModel;
use Atelier\Support\PrivateModel;

defined('ABSPATH') || exit;

/** A person's unavailability as a date range. Everyone is available unless an absence says otherwise. */
final class AbsenceModel
{
    public const POST_TYPE = 'absence';

    public const LOCKED = ['person', 'from', 'to'];
    public const PRIVATE = ['person', 'from', 'to', 'reason'];

    public static function register(array $config): void
    {
        PrivateModel::register(self::POST_TYPE, '_atelier_absence_', $config, self::fields(), self::LOCKED, [
            // The title is composed from the person and the dates, never typed.
            'supports'  => false,
            'menu_icon' => 'dashicons-hidden',
        ]);
    }

    private static function fields(): array
    {
        return [
            'person' => ['type' => 'relation', 'post_type' => PersonModel::POST_TYPE, 'multiple' => false, 'required' => true],
            'from'   => ['type' => 'date', 'required' => true],
            'to'     => ['type' => 'date', 'required' => true],
            'reason' => ['type' => 'text'],
        ];
    }
}
