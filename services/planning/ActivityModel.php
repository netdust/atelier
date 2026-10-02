<?php

declare(strict_types=1);

namespace Atelier\Planning;

use Atelier\Models\OrganisationModel;
use Atelier\Performance\PerformanceModel;
use Atelier\Production\ProductionModel;
use Atelier\Support\PrivateModel;
use Atelier\Support\Vocabulary;

defined('ABSPATH') || exit;

/** The dated unit of work: one date, one place, a kind and a status. */
final class ActivityModel
{
    public const POST_TYPE = 'activity';
    public const KINDS = ['travel', 'build', 'rehearsal', 'soundcheck', 'show', 'meal', 'overnight', 'strike', 'free', 'other'];

    /** First is the default the edit screen shows: a planner's entry is firm unless marked an option. */
    public const STATUSES = ['confirmed', 'option', 'cancelled'];
    public const SETTLED_BY = ['company', 'programmer'];

    public const LOCKED = ['date', 'kind', 'status', 'production'];

    /** Every field: nothing of a plan reaches the builder or REST. */
    public const PRIVATE = ['date', 'start', 'end', 'kind', 'status', 'production', 'performance', 'location', 'place', 'settled_by', 'notes'];

    public static function register(array $config): void
    {
        PrivateModel::register(self::POST_TYPE, '_atelier_activity_', $config, self::fields(), self::LOCKED, [
            'supports'  => ['title'],
            'menu_icon' => 'dashicons-calendar',
        ]);
    }

    private static function fields(): array
    {
        return [
            'date'        => ['type' => 'date', 'required' => true],
            'start'       => Vocabulary::time(),
            'end'         => Vocabulary::time(),
            'kind'        => Vocabulary::oneOf(self::KINDS),
            'status'      => Vocabulary::oneOf(self::STATUSES),
            'production'  => ['type' => 'relation', 'post_type' => ProductionModel::POST_TYPE, 'multiple' => false],
            'performance' => ['type' => 'relation', 'post_type' => PerformanceModel::POST_TYPE, 'multiple' => false],
            'location'    => ['type' => 'relation', 'post_type' => OrganisationModel::POST_TYPE, 'multiple' => false],
            'place'       => ['type' => 'text', 'description' => 'The room or spot when no organisation names it'],
            'settled_by'  => Vocabulary::oneOf(self::SETTLED_BY, false),
            'notes'       => ['type' => 'textarea'],
        ];
    }
}
