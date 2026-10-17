<?php

declare(strict_types=1);

namespace Atelier\PressKit;

defined('ABSPATH') || exit;

/**
 * A kit's zip on disk: each collection in its own folder, a collection's credits in a credits.txt beside
 * its files (R-3), a README.txt in a kit with no files (R-4). daan's, without its bio sheet and press
 * photo. WordPress-free: the caller resolves the attachments and the checked temp location; this writes
 * where it is told and reports skips rather than logging them.
 */
final class KitArchiveBuilder
{
    private const CREDITS = 'credits.txt';

    /** Already compressed: deflating these again costs time before the first byte and saves nothing. */
    private const STORED = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip', 'mp3', 'mp4'];

    /**
     * An entry without a label is named by its file.
     *
     * @param list<array{label: string, entries: list<array{ref: string, label: string, file: string, credit?: string}>}> $collections
     * @return array{path: string, skipped: array<string, string>}|null null when no zip was written
     */
    public static function build(string $path, array $collections): ?array
    {
        $zip = new \ZipArchive();

        if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $skipped = [];

        foreach ($collections as $collection) {
            self::addCollection($zip, $collection, $skipped);
        }

        // An archive with no entries is never written to disk, and the empty kit must still download.
        if ($zip->numFiles === 0) {
            $zip->addFromString('README.txt', "This kit has no files yet.\n");
        }

        self::compress($zip);

        // libzip writes, and reads every source, only now: a failure here leaves no zip to send.
        if (!$zip->close()) {
            return null;
        }

        return ['path' => $path, 'skipped' => $skipped];
    }

    /**
     * One folder per collection, named by its label, with its own name space.
     *
     * @param array{label: string, entries: list<array{ref: string, label: string, file: string, credit?: string}>} $collection
     * @param array<string, string> $skipped written by reference
     */
    private static function addCollection(\ZipArchive $zip, array $collection, array &$skipped): void
    {
        $names = new ArchiveNameSanitizer();
        $folder = (new ArchiveNameSanitizer())->sanitize($collection['label']);
        $credited = array_filter($collection['entries'], static fn(array $entry): bool => self::credit($entry) !== '');
        // Issued first, so a file of that name gets a suffix rather than the credits' place.
        $creditsName = $credited === [] ? null : $names->sanitize(self::CREDITS);
        $credits = '';

        foreach ($collection['entries'] as $entry) {
            // An attachment's bytes can be deleted, or shut, with its row left behind: a shorter zip,
            // never none — libzip would only find out at close(), and fail the whole zip.
            if (!is_file($entry['file'])) {
                $skipped[$entry['ref']] = 'has no file on disk';

                continue;
            }

            if (!is_readable($entry['file'])) {
                $skipped[$entry['ref']] = 'cannot be read';

                continue;
            }

            $name = $entry['label'] === ''
                ? $names->fromPath($entry['file'])
                : $names->sanitize(self::withExtension($entry['label'], $entry['file']));

            if (!$zip->addFile($entry['file'], $folder . '/' . $name)) {
                $skipped[$entry['ref']] = 'could not be added';

                continue;
            }

            $credit = self::credit($entry);

            if ($credit !== '') {
                $credits .= $name . ' — ' . $credit . "\n";
            }
        }

        if ($creditsName !== null && $credits !== '') {
            $zip->addFromString($folder . '/' . $creditsName, $credits);
        }
    }

    /** Each entry by its name's extension, which is its file's: media stored as it is, the rest deflated. */
    private static function compress(\ZipArchive $zip): void
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $extension = strtolower(pathinfo((string) $zip->getNameIndex($index), PATHINFO_EXTENSION));
            $zip->setCompressionIndex($index, in_array($extension, self::STORED, true) ? \ZipArchive::CM_STORE : \ZipArchive::CM_DEFLATE);
        }
    }

    /** A credit on one line: an editor's line break or control character cannot add a line of its own. */
    private static function credit(array $entry): string
    {
        $credit = mb_scrub((string) ($entry['credit'] ?? ''), 'UTF-8');

        return trim(preg_replace('/[\p{Cc}\p{Cf}\s]+/u', ' ', $credit) ?? '');
    }

    /** The entry always carries the real file's extension, so it opens by type. */
    private static function withExtension(string $label, string $file): string
    {
        $extension = (string) pathinfo($file, PATHINFO_EXTENSION);

        if ($extension === '' || strtolower((string) pathinfo($label, PATHINFO_EXTENSION)) === strtolower($extension)) {
            return $label;
        }

        return $label . '.' . $extension;
    }
}
