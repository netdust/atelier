<?php

declare(strict_types=1);

namespace Atelier\Planning;

use Atelier\Models\PersonModel;
use Atelier\Support\PrivateModel;
use Atelier\Support\Vocabulary;

defined('ABSPATH') || exit;

/** One person on one activity; the status is their answer or the planner's confirmation. */
final class AssignmentModel
{
    public const POST_TYPE = 'assignment';

    /** First is the default: the planner puts a person on, the person answers. */
    public const STATUSES = ['requested', 'confirmed', 'declined', 'maybe'];

    public const LOCKED = ['activity', 'status'];
    public const PRIVATE = ['activity', 'person', 'name', 'status', 'note'];

    public static function register(array $config): void
    {
        PrivateModel::register(self::POST_TYPE, '_atelier_assignment_', $config, self::fields(), self::LOCKED, [
            // The title is composed from the person and the activity, never typed.
            'supports'  => false,
            'menu_icon' => 'dashicons-admin-users',
        ]);
    }

    private static function fields(): array
    {
        return [
            'activity' => ['type' => 'relation', 'post_type' => ActivityModel::POST_TYPE, 'multiple' => false, 'required' => true],
            'person'   => ['type' => 'relation', 'post_type' => PersonModel::POST_TYPE, 'multiple' => false],
            'name'     => ['type' => 'text', 'description' => 'For someone without a record'],
            'status'   => Vocabulary::oneOf(self::STATUSES),
            'note'     => ['type' => 'text'],
        ];
    }
}
