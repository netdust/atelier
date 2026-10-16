<?php

declare(strict_types=1);

namespace Atelier\Card;

defined('ABSPATH') || exit;

/**
 * A person's vCard (RFC 6350), harvested from daan. Pure — no WordPress — so
 * the unit tier drives it. Every value is editor-authored: escapeValue() is
 * the one chokepoint that keeps a value on its own line. Its public statics
 * are the unit tier's seams.
 */
final class VCardBuilder
{
    /** RFC 6350 §3.2, in octets; a continuation's leading space counts toward it. */
    private const FOLD_LIMIT_OCTETS = 75;

    /** Strip control bytes, neutralise line terminators, then escape `\`, `;` and `,`. */
    public static function escapeValue(string $value): string
    {
        // The marker below is a control byte, so stripping them first means no input can forge one.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';

        $marker = "\x00";
        $neutralised = strtr($value, ["\r\n" => $marker, "\n\r" => $marker, "\r" => $marker, "\n" => $marker]);

        // strtr() never re-reads what it wrote, so each special is escaped exactly once.
        $escaped = strtr($neutralised, ['\\' => '\\\\', ';' => '\\;', ',' => '\\,']);

        // Emitted after the backslash pass, or the newline's own backslash would be doubled.
        return str_replace($marker, '\\n', $escaped);
    }

    /** Fold one content line at 75 octets, never inside a multi-byte character. */
    public static function foldLine(string $line): string
    {
        if (strlen($line) <= self::FOLD_LIMIT_OCTETS) {
            return $line;
        }

        $folded = mb_strcut($line, 0, self::FOLD_LIMIT_OCTETS, 'UTF-8');
        $rest = substr($line, strlen($folded));

        while ($rest !== '') {
            // '' only if one character were wider than the budget; take the rest rather than loop.
            $chunk = mb_strcut($rest, 0, self::FOLD_LIMIT_OCTETS - 1, 'UTF-8');
            $chunk = $chunk === '' ? $rest : $chunk;
            $folded .= "\r\n " . $chunk;
            $rest = substr($rest, strlen($chunk));
        }

        return $folded;
    }

    /**
     * One vCard record from the card's own keys — `name`, `given`, `family`,
     * `title`, `email`, `phone` and `company` (`name`, `url`). $source is never
     * iterated, so a wider map cannot widen the card.
     *
     * @param array<string, mixed> $source
     * @param bool $compact the QR's record: vCard 3.0, which phone cameras read best
     */
    public static function build(array $source, bool $compact = false): string
    {
        $company = is_array($source['company'] ?? null) ? $source['company'] : [];
        $name = self::escapeValue(self::readString($source, 'name'));
        $n = self::structuredName($source);
        $org = self::escapeValue(self::readString($company, 'name'));
        $title = self::escapeValue(self::readString($source, 'title'));

        $properties = ($compact
            ? ['N' => $n, 'FN' => $name, 'ORG' => $org, 'TITLE' => $title]
            : ['FN' => $name, 'N' => $n, 'TITLE' => $title, 'ORG' => $org]) + [
            'EMAIL' => self::escapeValue(self::readString($source, 'email')),
            'TEL'   => self::escapeValue(self::dialable(self::readString($source, 'phone'))),
            'URL'   => self::escapeValue(self::readString($company, 'url')),
        ];

        $lines = ['BEGIN:VCARD', $compact ? 'VERSION:3.0' : 'VERSION:4.0'];
        foreach ($properties as $property => $value) {
            if ($value !== '') {
                $lines[] = $property . ':' . $value;
            }
        }
        $lines[] = 'END:VCARD';

        $record = '';
        foreach ($lines as $line) {
            $record .= self::foldLine($line) . "\r\n";
        }

        return $record;
    }

    /** Digits, with a `+` kept only where the number starts with one. */
    public static function dialable(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        return str_starts_with($phone, '+') ? '+' . $digits : $digits;
    }

    /**
     * R-1: `Family;Given;;;` from the split the card model made; a one-word
     * name is all family. Each part is escaped before the separators are
     * written, so a `;` in a name cannot add a component.
     *
     * @param array<string, mixed> $source
     */
    private static function structuredName(array $source): string
    {
        $family = self::escapeValue(self::readString($source, 'family'));
        $given = self::escapeValue(self::readString($source, 'given'));

        return $family === '' && $given === '' ? '' : $family . ';' . $given . ';;;';
    }

    /**
     * One known key as a trimmed string; a non-scalar is '' — `(string)` of an
     * array publishes "Array", of an object it is a 500 on a public route.
     *
     * @param array<string, mixed> $source
     */
    private static function readString(array $source, string $key): string
    {
        $value = $source[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
