<?php

declare(strict_types=1);

namespace Atelier\Performance;

use Atelier\Production\ProductionRepository;

defined('ABSPATH') || exit;

/**
 * What narrows a calendar read — and the only code that interprets `$_GET`
 * (Threat model M1). Every value is scalar-checked, `wp_unslash()`ed once and
 * sanitized, then kept only when it passes its own allow-list; anything else
 * becomes null (or, for `past`, false) rather than reaching a query or an
 * echo.
 */
final class CalendarFilter
{
    /** The only query-string keys this filter ever reads. */
    public const PARAMS = ['show', 'audience', 'country', 'month', 'past'];

    private const AUDIENCES = ['public', 'school'];

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

    /**
     * The filter that drives every page-rendered view of the calendar — the
     * builder's own list (`resolve_calendar()`) and the filter form both read
     * the same state, so they never disagree. `$args` (the YOOtheme query's
     * own arguments) carried the only signal before this task; now the query
     * string is that signal everywhere, so `$args` is unused and kept only
     * for the call site's existing signature.
     *
     * @param array<string, mixed> $args Unused — kept for call-site compatibility.
     */
    public static function fromRequest(array $args = []): self
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
        $audience = in_array($audience, self::AUDIENCES, true) ? $audience : null;

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
     * The kept values, keyed the way `fromQuery()` reads them back — the
     * form's selected state and the feed link both build off this, so
     * nothing unlisted ever finds its way back into a URL.
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

    /** A scalar value, `wp_unslash()`ed once — an array (or anything else non-scalar) is dropped. */
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
