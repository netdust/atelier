<?php

declare(strict_types=1);

namespace Atelier\Performance;

use DateTimeImmutable;
use DateTimeZone;

defined('ABSPATH') || exit;

/**
 * RFC 5545 writer, no WordPress calls. Every value goes through text() and only its own property
 * names are emitted, so stored content can never forge a second VEVENT (Threat 3 / M5).
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
     * RFC 5545 §3.3.11. `\` before `;`/`,` so their inserted backslashes aren't doubled; CR/LF become
     * a literal `\n`, so a stored value can never start a content line of its own (Threat 3).
     */
    public static function text(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace([';', ','], ['\;', '\,'], $value);
        $value = str_replace(["\r\n", "\r", "\n"], '\n', $value);

        return (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $value);
    }

    /**
     * RFC 5545 §3.1 fold at 75 octets, never inside a UTF-8 character. A back-off that reaches 0
     * (a run of stray continuation bytes) cuts at full width instead, or the loop would never progress.
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
