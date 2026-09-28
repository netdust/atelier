<?php

declare(strict_types=1);

namespace Atelier\Support;

defined('ABSPATH') || exit;

/**
 * A site may relabel or extend an atelier model's fields, but not remove the
 * ones atelier's own code reads: a production without `premiere_year` would
 * lose its year from every list and its JSON-LD without an error.
 */
final class LockedFields
{
    /** @var array<string, array{defaults: array, locked: list<string>}> */
    private static array $guarded = [];

    public static function guard(string $postType, array $defaults, array $locked): void
    {
        $alreadyHooked = isset(self::$guarded[$postType]);
        self::$guarded[$postType] = ['defaults' => $defaults, 'locked' => $locked];

        if ($alreadyHooked) {
            return;
        }

        add_filter("ntdst/{$postType}/fields", static function (array $fields) use ($postType): array {
            return self::restore($postType, $fields);
        }, PHP_INT_MAX);
    }

    private static function restore(string $postType, array $fields): array
    {
        ['defaults' => $defaults, 'locked' => $locked] = self::$guarded[$postType];

        foreach ($locked as $key) {
            if (isset($fields[$key]) || !isset($defaults[$key])) {
                continue;
            }

            $fields[$key] = $defaults[$key];
            ntdst_log('atelier')->warning(sprintf(
                'A site removed the locked field "%s" from "%s"; atelier restored it.',
                $key,
                $postType,
            ));
        }

        return $fields;
    }
}
