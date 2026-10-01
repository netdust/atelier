<?php

declare(strict_types=1);

namespace Atelier\Support;

use Atelier\Models\OrganisationModel;
use Atelier\Models\PersonModel;
use Atelier\Performance\PerformanceModel;
use Atelier\Planning\AbsenceModel;
use Atelier\Planning\ActivityModel;
use Atelier\Planning\AssignmentModel;

defined('ABSPATH') || exit;

/**
 * Fields that stay out of the YOOtheme builder. REST is the privacy boundary
 * (they are never show_in_rest); this keeps them out of the Dynamic Content
 * picker, so no template can bind them by accident.
 */
final class PrivateFields
{
    private const BY_MODEL = [
        PersonModel::POST_TYPE       => PersonModel::PRIVATE,
        OrganisationModel::POST_TYPE => OrganisationModel::PRIVATE,
        PerformanceModel::POST_TYPE  => PerformanceModel::PRIVATE,
        // Every planning field: the whole model stays out of the builder.
        ActivityModel::POST_TYPE     => ActivityModel::PRIVATE,
        AssignmentModel::POST_TYPE   => AssignmentModel::PRIVATE,
        AbsenceModel::POST_TYPE      => AbsenceModel::PRIVATE,
    ];

    /**
     * @param list<string> $excluded
     * @return list<string>
     */
    public static function exclude(array $excluded, string $model): array
    {
        return array_values(array_unique(array_merge($excluded, self::BY_MODEL[$model] ?? [])));
    }
}
