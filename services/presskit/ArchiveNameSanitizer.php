<?php

declare(strict_types=1);

namespace Atelier\PressKit;

defined('ABSPATH') || exit;

/**
 * Makes an editor's file name safe as a zip entry and as the Content-Disposition filename (threat
 * model attack 9). daan's, keeping more of what an editor types — apostrophes, commas, `&`, `!`, dashes —
 * with runs of whitespace as one space. Pure: no WordPress, no globals, no filesystem.
 */
final class ArchiveNameSanitizer
{
    /**
     * Anything not matched survives; everything else is dropped. An allow-list because a deny-list would
     * have to enumerate every future direction override, separator dialect and control byte: `"`, `\`,
     * `/`, and every \p{Cc} and \p{Cf} stay out. `\p{M}` is load-bearing: a decomposed `Daniël` carries
     * its diaeresis as a combining mark. Whitespace survives here only to be collapsed next.
     */
    private const SAFE_CHARACTERS = "/[^\\p{L}\\p{M}\\p{N}\\p{Pd}\\s\\p{Z}._()'\u{2018}\u{2019},&!]/u";

    private const WHITESPACE = '/[\s\p{Z}]+/u';

    private const FALLBACK_STEM = 'file';

    /** @var array<string, int> entry name => highest suffix issued against it */
    private array $issued = [];

    /** One instance is one archive's name space: collisions dedupe within it, never across instances. */
    public function sanitize(string $candidate): string
    {
        // Scrub first: preg_* with /u returns null on invalid UTF-8. Basename before the strip, not after:
        // the strip removes the separators, so `/etc/passwd` stripped first is `etcpasswd`.
        $segment = $this->lastPathSegment(mb_scrub($candidate, 'UTF-8'));

        // Fail closed to the fallback rather than to the raw name. A CR, LF or tab becomes a space.
        $safe = preg_replace(self::WHITESPACE, ' ', preg_replace(self::SAFE_CHARACTERS, '', $segment) ?? '') ?? '';

        [$stem, $extension] = $this->splitExtension($safe);

        return $this->issue($stem, $extension);
    }

    private function lastPathSegment(string $candidate): string
    {
        $segments = preg_split('#[/\\\\]+#', $candidate, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $segments === [] ? '' : (string) end($segments);
    }

    /** @return array{0: string, 1: string} the stem (never empty), the extension with its leading dot */
    private function splitExtension(string $name): array
    {
        $dot = strrpos($name, '.');
        $extension = $dot === false ? '' : substr($name, $dot);

        // `.` and `..` are path segments, not extensions: dots alone leave nothing to open by type.
        if (trim($extension, '.') === '') {
            $extension = '';
            $dot = false;
        }

        // Trimming dots keeps the entry off `.`, `..` and dot-leading hidden names; trimming spaces stops
        // a whitespace-only stem passing as non-empty.
        $stem = trim($dot === false ? $name : substr($name, 0, $dot), ' .');

        return [$stem === '' ? self::FALLBACK_STEM : $stem, $extension];
    }

    /** An unused entry name, suffixed before the extension so a de-duplicated entry still opens by type. */
    private function issue(string $stem, string $extension): string
    {
        $entry = $stem . $extension;

        if (!isset($this->issued[$entry])) {
            $this->issued[$entry] = 0;

            return $entry;
        }

        // Resume from the last suffix issued against this base, past any suffixed name supplied directly.
        // Sequential, so a replayed input sequence replays the same output sequence.
        $suffix = $this->issued[$entry];

        do {
            $suffix++;
            $issued = $stem . '-' . $suffix . $extension;
        } while (isset($this->issued[$issued]));

        $this->issued[$entry] = $suffix;
        $this->issued[$issued] = 0;

        return $issued;
    }
}
