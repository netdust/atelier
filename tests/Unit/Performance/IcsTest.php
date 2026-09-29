<?php

declare(strict_types=1);

namespace AtelierTests\Unit\Performance;

use Atelier\Performance\Ics;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Ics is pure PHP with no WordPress calls — the writer for `/speellijst.ics/`
 * (Threat model M5). text() and fold() are the two functions the threat model
 * names by name: text() is what stops a stored title or venue name forging a
 * second VEVENT (Threat 3, F4), fold() is what keeps a long line from
 * splitting a UTF-8 character in two.
 */
final class IcsTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     * @return array{uid:string, start:DateTimeImmutable, all_day:bool, minutes:?int, summary:string, location:string, url:string, cancelled:bool, stamp:DateTimeImmutable}
     */
    private function event(array $overrides = []): array
    {
        return array_merge([
            'uid'       => 'performance-1@laika.test',
            'start'     => new DateTimeImmutable('2026-10-01 20:00', new DateTimeZone('UTC')),
            'all_day'   => false,
            'minutes'   => null,
            'summary'   => 'Wat de koe zag',
            'location'  => 'CC Foo, Kerkstraat 1, Gent, Belgium',
            'url'       => 'https://laika.be/voorstellingen/wat-de-koe-zag/',
            'cancelled' => false,
            'stamp'     => new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC')),
        ], $overrides);
    }

    public function test_text_escapes_backslash_first_and_neutralises_newlines(): void
    {
        $this->assertSame(
            'a\\\\b\;c\,d\nEND:VEVENT',
            Ics::text("a\\b;c,d\r\nEND:VEVENT"),
        );
    }

    public function test_fold_never_splits_a_multibyte_character(): void
    {
        $line = str_repeat('é', 100); // 100 * 2 octets = 200 octets

        $folded = Ics::fold($line);

        foreach (explode("\r\n", $folded) as $physical) {
            $this->assertLessThanOrEqual(75, strlen($physical));

            $content = str_starts_with($physical, ' ') ? substr($physical, 1) : $physical;
            $this->assertTrue(mb_check_encoding($content, 'UTF-8'), 'physical line is valid UTF-8: ' . bin2hex($physical));
        }

        // Unfolding (strip the CRLF + single leading space of every
        // continuation) must reproduce the original line exactly.
        $unfolded = implode('', array_map(
            static fn(string $physical): string => str_starts_with($physical, ' ') ? substr($physical, 1) : $physical,
            explode("\r\n", $folded),
        ));
        $this->assertSame($line, $unfolded);
    }

    public function test_timed_event_is_utc_across_dst(): void
    {
        $beforeDstEnds = new DateTimeImmutable('2026-10-24 20:00', new DateTimeZone('Europe/Brussels'));
        $afterDstEnds = new DateTimeImmutable('2026-10-25 20:00', new DateTimeZone('Europe/Brussels'));

        $ics = Ics::calendar([
            $this->event(['uid' => 'before', 'start' => $beforeDstEnds]),
            $this->event(['uid' => 'after', 'start' => $afterDstEnds]),
        ], 'Speeldata');

        $this->assertStringContainsString('DTSTART:20261024T180000Z', $ics);
        $this->assertStringContainsString('DTSTART:20261025T190000Z', $ics);
    }

    public function test_all_day_event_uses_value_date(): void
    {
        $ics = Ics::calendar([$this->event([
            'all_day' => true,
            'start'   => new DateTimeImmutable('2026-10-25 00:00', new DateTimeZone('Europe/Brussels')),
        ])], 'Speeldata');

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261025', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20261026', $ics);
        $this->assertStringNotContainsString('DTSTART:2026', $ics);
    }

    public function test_cancelled_event_has_status(): void
    {
        $ics = Ics::calendar([$this->event(['cancelled' => true])], 'Speeldata');

        $this->assertStringContainsString('STATUS:CANCELLED', $ics);
    }

    public function test_lines_end_in_crlf(): void
    {
        $ics = Ics::calendar([$this->event()], 'Speeldata');

        $this->assertStringEndsWith("\r\n", $ics);

        foreach (explode("\r\n", $ics) as $piece) {
            $this->assertStringNotContainsString("\n", $piece);
            $this->assertStringNotContainsString("\r", $piece);
        }
    }

    /**
     * Threat 3 on the writer's own output, not just text(): a forged
     * `END:VEVENT\r\nBEGIN:VEVENT` inside a summary must not add a second
     * event to a calendar of one. `BEGIN:VEVENT` surviving as ASCII text
     * INSIDE the escaped SUMMARY value is expected and harmless — a calendar
     * parser reads logical (unfolded) lines, so what matters is that only one
     * of them IS `BEGIN:VEVENT`, not that the substring never appears.
     */
    public function test_forged_content_cannot_add_a_second_event(): void
    {
        $ics = Ics::calendar([$this->event([
            'summary' => "X\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:forged",
        ])], 'Speeldata');

        $logical = $this->unfold($ics);

        $this->assertCount(1, array_filter($logical, static fn(string $l): bool => $l === 'BEGIN:VEVENT'));
        $this->assertCount(1, array_filter($logical, static fn(string $l): bool => $l === 'END:VEVENT'));
        $this->assertNotContains('UID:forged', $logical);
    }

    /**
     * RFC 5545 §3.1 unfolding: a continuation physical line opens with a
     * single space, which is stripped and the line joined onto the previous
     * one.
     *
     * @return list<string>
     */
    private function unfold(string $ics): array
    {
        $logical = [];

        foreach (explode("\r\n", rtrim($ics, "\r\n")) as $physical) {
            if ($physical !== '' && $physical[0] === ' ') {
                $logical[count($logical) - 1] .= substr($physical, 1);
            } else {
                $logical[] = $physical;
            }
        }

        return $logical;
    }
}
