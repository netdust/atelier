<?php

declare(strict_types=1);

namespace Atelier\Performance;

use Atelier\Production\ProductionRepository;

defined('ABSPATH') || exit;

/**
 * What narrows a calendar read, and the only code that interprets `$_GET` (M1): every value is
 * scalar-checked, unslashed once, sanitized, then kept only when it passes its allow-list.
 */
final class CalendarFilter
{
    /** The only query-string keys this filter reads. */
    public const PARAMS = ['show', 'audience', 'country', 'month', 'past'];

    /**
     * The model's audiences minus `closed`, which is never listed publicly. The form offers exactly these.
     *
     * @return list<string>
     */
    public static function audiences(): array
    {
        return array_values(array_diff(PerformanceModel::AUDIENCES, ['closed']));
    }

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

    public static function past(): self
    {
        return new self(null, null, null, null, true);
    }

    public static function forProduction(int $id): self
    {
        return new self($id, null, null, null, false);
    }

    public static function fromRequest(): self
    {
        return self::fromQuery($_GET);
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): self
    {
        $show = self::scalar($query, 'show');
        $production = $show !== null ? self::resolveProduction(sanitize_title($show)) : null;

        $audience = self::scalar($query, 'audience');
        $audience = $audience !== null ? sanitize_key($audience) : null;
        $audience = in_array($audience, self::audiences(), true) ? $audience : null;

        $country = self::scalar($query, 'country');
        $country = $country !== null ? sanitize_text_field($country) : null;
        $country = ($country !== null && in_array($country, self::knownCountries(), true)) ? $country : null;

        $month = self::scalar($query, 'month');
        $month = $month !== null ? sanitize_text_field($month) : null;
        $month = self::validMonth($month) ? $month : null;

        $past = self::scalar($query, 'past') === '1';

        return new self($production, $audience, $country, $month, $past);
    }

    /**
     * The kept values, keyed as `fromQuery()` reads them, so nothing unlisted reaches a URL.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        $query = [];

        if ($this->production !== null) {
            $post = ntdst_get(ProductionRepository::class)->find($this->production);
            if (!is_wp_error($post)) {
                $query['show'] = (string) $post->post_name;
            }
        }

        if ($this->audience !== null) {
            $query['audience'] = $this->audience;
        }

        if ($this->country !== null) {
            $query['country'] = $this->country;
        }

        if ($this->month !== null) {
            $query['month'] = $this->month;
        }

        if ($this->past) {
            $query['past'] = '1';
        }

        return $query;
    }

    private static function scalar(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return is_scalar($value) ? (string) wp_unslash($value) : null;
    }

    private static function resolveProduction(string $slug): ?int
    {
        $post = ntdst_get(ProductionRepository::class)->findPublishedBySlug($slug);

        return $post?->ID;
    }

    /**
     * @return list<string>
     */
    private static function knownCountries(): array
    {
        return ntdst_get(PerformanceRepository::class)->countries();
    }

    private static function validMonth(?string $month): bool
    {
        if ($month === null || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            return false;
        }

        return checkdate((int) substr($month, 5, 2), 1, (int) substr($month, 0, 4));
    }
}
