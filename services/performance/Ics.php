<?php

declare(strict_types=1);

namespace Atelier\Performance;

use DateTimeImmutable;
use DateTimeZone;

defined('ABSPATH') || exit;

/**
 * A pure-PHP iCalendar (RFC 5545) writer — no WordPress calls, unit tested
 * directly. The only code that turns performance data into calendar bytes:
 * every value goes through text() before it reaches a content line (Threat 3
 * / M5), and the writer emits only its own property names, so a forged
 * `END:VEVENT\r\nBEGIN:VEVENT…` stored in a title or venue name can never
 * become a second event or property.
 */
final class Ics
{
    private const CRLF = "\r\n";

    /**
     * @param list<array{uid:string, start:DateTimeImmutable, all_day:bool, minutes:?int, summary:string, location:string, url:string, cancelled:bool, stamp:DateTimeImmutable}> $events
     */
    public static function calendar(array $events, string $name): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//atelier//calendar//EN',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:' . self::text($name),
        ];

        foreach ($events as $event) {
            array_push($lines, ...self::event($event));
        }

        $lines[] = 'END:VCALENDAR';

        $output = '';
        foreach ($lines as $line) {
            $output .= self::fold($line) . self::CRLF;
        }

        return $output;
    }

    /**
     * @param array{uid:string, start:DateTimeImmutable, all_day:bool, minutes:?int, summary:string, location:string, url:string, cancelled:bool, stamp:DateTimeImmutable} $event
     *
     * @return list<string>
     */
    private static function event(array $event): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . self::text($event['uid']),
            'DTSTAMP:' . self::utc($event['stamp']),
        ];

        if ($event['all_day']) {
            $lines[] = 'DTSTART;VALUE=DATE:' . $event['start']->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $event['start']->modify('+1 day')->format('Ymd');
        } else {
            $lines[] = 'DTSTART:' . self::utc($event['start']);

            if ($event['minutes'] !== null) {
                $lines[] = 'DURATION:PT' . $event['minutes'] . 'M';
            }
        }

        $lines[] = 'SUMMARY:' . self::text($event['summary']);

        if ($event['location'] !== '') {
            $lines[] = 'LOCATION:' . self::text($event['location']);
        }

        if ($event['url'] !== '') {
            $lines[] = 'URL:' . self::text($event['url']);
        }

        if ($event['cancelled']) {
            $lines[] = 'STATUS:CANCELLED';
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private static function utc(DateTimeImmutable $when): string
    {
        return $when->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * Escapes a value for a content line (RFC 5545 §3.3.11). `mb_scrub()`
     * first — the calendar's own charset is UTF-8, and a value that reached
     * storage some other way than this writer (a direct SQL import; Threat
     * 3's own example) can carry bytes that are not valid UTF-8 at all,
     * which both breaks the output for a real parser and is what used to
     * send fold()'s cut point past 0 (see fold()). Then `\` — escaping it
     * after `;`/`,` would double-escape the backslashes those steps just
     * inserted — then `;` and `,`, then every CRLF, CR and LF collapses to a
     * literal `\n`. That last step is the one that matters for Threat 3: a
     * stored value can carry a real line break, but never an actual CR or LF
     * byte, so it can never start a new content line, let alone
     * `END:VEVENT` / `BEGIN:VEVENT`.
     */
    public static function text(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace([';', ','], ['\;', '\,'], $value);

        return str_replace(["\r\n", "\r", "\n"], '\n', $value);
    }

    /**
     * Folds one content line at 75 octets (RFC 5545 §3.1): every physical
     * line but the last ends in CRLF, and every line after the first opens
     * with one space, itself counted inside that line's 75 octets. The cut
     * point backs off a UTF-8 continuation byte (10xxxxxx) so a multibyte
     * character is always kept whole on one physical line — but a run of
     * ≥75 continuation bytes with no lead byte in front of them (invalid
     * UTF-8; text() scrubs this before it reaches here, but fold() is
     * public on its own) would back the cut off all the way to 0, which
     * cuts NOTHING and never shrinks $rest. Every call must make progress,
     * so a back-off that reaches 0 falls back to the full width instead.
     */
    public static function fold(string $line): string
    {
        $folded = '';
        $rest = $line;
        $first = true;

        while (strlen($rest) > ($first ? 75 : 74)) {
            $limit = $first ? 75 : 74;
            $cut = $limit;

            while ($cut > 0 && (ord($rest[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }

            if ($cut === 0) {
                $cut = $limit;
            }

            $folded .= ($first ? '' : ' ') . substr($rest, 0, $cut) . self::CRLF;
            $rest = substr($rest, $cut);
            $first = false;
        }

        return $folded . ($first ? '' : ' ') . $rest;
    }
}
