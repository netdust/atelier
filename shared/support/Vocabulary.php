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
}
