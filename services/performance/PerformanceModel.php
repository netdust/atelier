<?php

declare(strict_types=1);

namespace Atelier\Performance;

use Atelier\Models\OrganisationModel;
use Atelier\Production\ProductionModel;
use Atelier\Support\PublicModel;
use Atelier\Support\Vocabulary;

defined('ABSPATH') || exit;

final class PerformanceModel
{
    public const POST_TYPE = 'performance';
    public const AUDIENCES = ['public', 'school', 'closed'];
    public const TICKET_STATUSES = ['announced', 'on_sale', 'few_left', 'sold_out', 'cancelled'];

    /** A site relabels these, never drops them: without a date or venue a performance is unreadable. */
    public const LOCKED = ['production', 'venue', 'date', 'audience'];

    /** Empty until backstage-schedule adds its fields to this post (D8). */
    public const PRIVATE = [];

    public static function register(array $config): void
    {
        PublicModel::register(self::POST_TYPE, '_atelier_performance_', $config, self::fields(), self::LOCKED, [
            // No permalink: a performance is a date, read through the
            // calendar and its feed, never its own page.
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'rewrite'             => false,
            'supports'            => ['title'],
            'menu_icon'           => 'dashicons-calendar-alt',
        ]);
    }

    private static function fields(): array
    {
        return [
            'production' => ['type' => 'relation', 'post_type' => ProductionModel::POST_TYPE, 'multiple' => false, 'required' => true],
            'venue'      => ['type' => 'relation', 'post_type' => OrganisationModel::POST_TYPE, 'multiple' => false, 'required' => true],
            'date'       => ['type' => 'date', 'required' => true],
            'time'       => Vocabulary::time(),
            'audience'         => Vocabulary::oneOf(self::AUDIENCES),
            'language_version' => ['type' => 'text'],
            'ticket_url'       => ['type' => 'url'],
            'ticket_status'    => Vocabulary::oneOf(self::TICKET_STATUSES, false),
        ];
    }
}
