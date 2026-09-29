<?php

declare(strict_types=1);

namespace Atelier\Performance;

defined('ABSPATH') || exit;

/**
 * What narrows a calendar read. `production`, `audience`, `country` and
 * `month` are declared for the shape Task 2 fills; this task only reads
 * `past` — the upcoming/past split `fromRequest()` and the two factories
 * below give behaviour to.
 */
final class CalendarFilter
{
    private function __construct(
        public readonly ?int $production,
        public readonly ?string $audience,
        public readonly ?string $country,
        public readonly ?string $month,
        public readonly bool $past,
    ) {
    }

    public static function upcoming(): self
    {
        return new self(null, null, null, null, false);
    }

    public static function forProduction(int $id): self
    {
        return new self($id, null, null, null, false);
    }

    /**
     * Read from the builder query's own arguments (or the feed's raw request,
     * Task 2). Only `past` has meaning here.
     *
     * @param array<string, mixed> $args
     */
    public static function fromRequest(array $args): self
    {
        return new self(null, null, null, null, !empty($args['past']));
    }
}
