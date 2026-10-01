<?php

declare(strict_types=1);

namespace Atelier\Support;

defined('ABSPATH') || exit;

/** A fixed set of option strings, shared by every atelier model that has one. */
final class Vocabulary
{
    /**
     * `select` sanitizes but does not validate: its options are a UI list, and
     * a value outside them would drop a row from every list silently.
     *
     * @param list<string> $values
     */
    public static function oneOf(array $values, bool $required = true): array
    {
        return [
            'type'     => 'select',
            'required' => $required,
            'options'  => array_combine($values, $values),
            'validate' => static fn($value) => in_array($value, $values, true) ?: sprintf('must be one of: %s', implode(', ', $values)),
        ];
    }

    /** A time of day as a text fact: `HH:MM`, 24-hour, or empty for "all day". */
    public static function time(bool $required = false): array
    {
        return [
            'type'        => 'text',
            'required'    => $required,
            'translate'   => false,
            'description' => 'HH:MM, 24-hour. Empty means all-day.',
            'validate'    => static fn($value) => ($value === '' || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $value) === 1)
                ?: 'time must be HH:MM (24-hour) or empty',
        ];
    }
}
