<?php

declare(strict_types=1);

namespace AtelierTests\Unit\PressKit;

use Atelier\PressKit\ArchiveNameSanitizer;
use Atelier\PressKit\KitArchiveBuilder;
use PHPUnit\Framework\TestCase;

/**
 * A kit's zip, ported from daan's KitArchiveBuilderTest and ArchiveNameSanitizerTest: each collection in
 * its own folder, every name an editor typed made safe for a zip entry and a header (attack 9), photo
 * credits in a credits.txt beside the photos (R-3), and a README.txt in an empty kit (R-4).
 */
final class KitArchiveBuilderTest extends TestCase
{
    private const README = 'This kit has no files yet.';

    private string $assets = '';

    private string $destination = '';

    private int $built = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $root = sys_get_temp_dir() . '/laika-kit-' . bin2hex(random_bytes(6));
        $this->assets = $root . '/assets';
        $this->destination = $root . '/out';

        $this->assertTrue(mkdir($this->assets, 0777, true));
        $this->assertTrue(mkdir($this->destination));
    }

    protected function tearDown(): void
    {
        foreach ([$this->assets, $this->destination] as $directory) {
            chmod($directory, 0777);
            foreach (glob($directory . '/*') ?: [] as $file) {
                chmod($file, 0666);
                unlink($file);
            }
            rmdir($directory);
        }

        rmdir(dirname($this->assets));

        parent::tearDown();
    }

    public function test_every_file_becomes_an_entry_in_its_collections_folder(): void
    {
        $logo = $this->asset('laika.svg');

        $zip = $this->build([
            $this->collection('Logos', [$this->entry('Laika', $logo)]),
            $this->collection('Documenten', [$this->entry('Dossier', $this->asset('dossier.pdf'))]),
        ]);

        $this->assertSame(['Logos/Laika.svg', 'Documenten/Dossier.pdf'], $this->names($zip));
        $this->assertSame($logo['bytes'], $zip->getFromName('Logos/Laika.svg'), "the entry is the file's own bytes");
    }

    public function test_an_entry_carries_its_files_extension_once(): void
    {
        $zip = $this->build([$this->collection('Documenten', [
            $this->entry('Dossier', $this->asset('dossier.pdf')),
            $this->entry('Rider.pdf', $this->asset('rider.pdf')),
        ])]);

        $this->assertSame(['Documenten/Dossier.pdf', 'Documenten/Rider.pdf'], $this->names($zip));
    }

    public function test_photo_credits_are_written_beside_the_photos(): void
    {
        $zip = $this->build([
            $this->collection('Fotos', [
                $this->entry('koe.jpg', $this->asset('koe.jpg'), 'Foto: An Smet'),
                $this->entry('stal.jpg', $this->asset('stal.jpg')),
                $this->entry('kip.jpg', $this->asset('kip.jpg'), 'Foto: Jo Roets'),
            ]),
            $this->collection('Documenten', [$this->entry('Dossier', $this->asset('dossier.pdf'))]),
        ]);

        $this->assertSame(
            ['Fotos/koe.jpg', 'Fotos/stal.jpg', 'Fotos/kip.jpg', 'Fotos/credits.txt', 'Documenten/Dossier.pdf'],
            $this->names($zip),
            'one credits.txt, beside the photos; a folder with no credit has none',
        );
        $this->assertSame(
            "koe.jpg — Foto: An Smet\nkip.jpg — Foto: Jo Roets\n",
            $zip->getFromName('Fotos/credits.txt'),
            'each credited photo by the name it has in the zip; an uncredited one is not listed',
        );
    }

    public function test_a_credit_stays_on_its_own_line(): void
    {
        $zip = $this->build([$this->collection('Fotos', [
            $this->entry('koe.jpg', $this->asset('koe.jpg'), "Foto:\r\nAn\tSmet\u{202E} "),
        ])]);

        $this->assertSame("koe.jpg — Foto: An Smet\n", $zip->getFromName('Fotos/credits.txt'));
    }

    public function test_a_photo_named_like_the_credits_does_not_replace_them(): void
    {
        $zip = $this->build([$this->collection('Fotos', [
            $this->entry('credits.txt', $this->asset('decoy.txt'), 'Foto: An Smet'),
        ])]);

        $this->assertSame(['Fotos/credits-1.txt', 'Fotos/credits.txt'], $this->names($zip));
        $this->assertSame("credits-1.txt — Foto: An Smet\n", $zip->getFromName('Fotos/credits.txt'));
    }

    public function test_the_builder_writes_to_the_path_it_was_handed_and_no_other(): void
    {
        $path = $this->path();

        $photos = $this->collection('Fotos', [$this->entry('koe.jpg', $this->asset('koe.jpg'))]);
        $result = KitArchiveBuilder::build($path, [$photos], self::README);

        $this->assertIsArray($result);
        $this->assertSame($path, $result['path']);
        $written = glob($this->destination . '/*') ?: [];
        $this->assertSame([$path], $written, 'the zip stays where its caller checked it may be');
    }

    public function test_colliding_names_become_distinct_entries(): void
    {
        $zip = $this->build([$this->collection('Documenten', [
            $this->entry('Dossier', $this->asset('one.pdf')),
            $this->entry('Dossier', $this->asset('two.pdf')),
        ])]);

        $this->assertSame(['Documenten/Dossier.pdf', 'Documenten/Dossier-1.pdf'], $this->names($zip));
    }

    public function test_each_archive_names_from_scratch(): void
    {
        $collections = [$this->collection('Documenten', [
            $this->entry('Dossier', $this->asset('one.pdf')),
            $this->entry('Dossier', $this->asset('two.pdf')),
        ])];

        $fresh = ['Documenten/Dossier.pdf', 'Documenten/Dossier-1.pdf'];
        $this->assertSame($fresh, $this->names($this->build($collections)));
        $this->assertSame($fresh, $this->names($this->build($collections)), 'the second zip starts from scratch');
    }

    public function test_a_missing_file_is_skipped_and_reported(): void
    {
        $gone = $this->asset('gone.jpg');
        unlink($gone['path']);

        $result = KitArchiveBuilder::build($this->path(), [$this->collection('Fotos', [
            $this->entry('een.jpg', $this->asset('een.jpg')),
            $this->entry('gone.jpg', $gone, 'Foto: niemand', '4242'),
            $this->entry('twee.jpg', $this->asset('twee.jpg')),
        ])], self::README);

        $this->assertIsArray($result);
        $zip = $this->open($result['path']);
        $this->assertSame(['Fotos/een.jpg', 'Fotos/twee.jpg'], $this->names($zip), 'the rest of the kit still ships');
        $this->assertSame(['4242' => 'has no file on disk'], $result['skipped'], 'reported by the caller’s reference');
    }

    /** libzip reads a source only at close(): an asset it cannot read is skipped up front, never an empty zip. */
    public function test_an_unreadable_file_is_skipped_and_reported(): void
    {
        $locked = $this->asset('locked.jpg');
        chmod($locked['path'], 0000);
        $this->assertFalse(is_readable($locked['path']), 'the tier runs as a user a 0000 file shuts out');

        $result = KitArchiveBuilder::build($this->path(), [$this->collection('Fotos', [
            $this->entry('een.jpg', $this->asset('een.jpg')),
            $this->entry('locked.jpg', $locked, 'Foto: niemand', '4343'),
            $this->entry('twee.jpg', $this->asset('twee.jpg')),
        ])], self::README);

        $this->assertIsArray($result);
        $this->assertSame(['Fotos/een.jpg', 'Fotos/twee.jpg'], $this->names($this->open($result['path'])));
        $this->assertSame(['4343' => 'cannot be read'], $result['skipped']);
    }

    /** close() is where libzip writes: when it fails there is no zip, and the caller must not send one. */
    public function test_a_zip_that_cannot_be_written_fails_closed(): void
    {
        $path = $this->path();
        chmod($this->destination, 0555);
        $warnings = [];
        set_error_handler(static function (int $no, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, E_WARNING);

        try {
            $photos = $this->collection('Fotos', [$this->entry('koe.jpg', $this->asset('koe.jpg'))]);
            $result = KitArchiveBuilder::build($path, [$photos], self::README);
        } finally {
            restore_error_handler();
        }

        $this->assertNull($result, 'a destination libzip cannot write at close()');
        $this->assertNotSame([], $warnings, 'PHP said why');
    }

    public function test_a_collection_whose_files_all_vanished_leaves_no_folder(): void
    {
        $gone = $this->asset('gone.jpg');
        unlink($gone['path']);

        $zip = $this->build([$this->collection('Fotos', [$this->entry('gone.jpg', $gone, 'Foto: niemand')])]);

        $this->assertSame(['README.txt'], $this->names($zip));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function editorNames(): array
    {
        return [
            // Kept out. A separator in a name is a word there, not a path: only dot segments go.
            'traversal'         => ['../../wp-config', 'Documenten/wp-config.pdf'],
            'windows path'      => ['C:\\Windows\\system', 'Documenten/C - Windows - system.pdf'],
            'slash'             => ['Dossier 2024/2025', 'Documenten/Dossier 2024 - 2025.pdf'],
            'backslash'         => ['AC\\DC', 'Documenten/AC - DC.pdf'],
            'header split'      => ["Dossier\r\n", 'Documenten/Dossier.pdf'],
            'quotes'            => ['Het "grote" dossier', 'Documenten/Het grote dossier.pdf'],
            'control character' => ["Dossier\x07", 'Documenten/Dossier.pdf'],
            'format character'  => ["Dos\u{202E}sier", 'Documenten/Dossier.pdf'],
            'parent segment'    => ['..', 'Documenten/file.pdf'],
            // No label: the entry is named by its file.
            'empty'             => ['', 'Documenten/dossier.pdf'],
            // Kept, as the editor typed them.
            'accents'           => ['Daniël (2024)', 'Documenten/Daniël (2024).pdf'],
            'apostrophe'        => ["L'Oiseau bleu", "Documenten/L'Oiseau bleu.pdf"],
            'right quote'       => ["L\u{2019}Oiseau bleu", "Documenten/L\u{2019}Oiseau bleu.pdf"],
            'left quote'        => ["\u{2018}t Stad", "Documenten/\u{2018}t Stad.pdf"],
            'comma'             => ['Rider, techniek', 'Documenten/Rider, techniek.pdf'],
            'ampersand'         => ['Jo & Peter', 'Documenten/Jo & Peter.pdf'],
            'exclamation'       => ['Pas op!', 'Documenten/Pas op!.pdf'],
            'en dash'           => ["2024\u{2013}2025", "Documenten/2024\u{2013}2025.pdf"],
            'em dash'           => ["Koe \u{2014} kip", "Documenten/Koe \u{2014} kip.pdf"],
            // Runs of whitespace, a tab and a no-break space among them, become one space.
            'whitespace'        => ["Het   grote\tdossier", 'Documenten/Het grote dossier.pdf'],
            'no-break space'    => ["Jo\u{00A0}Roets", 'Documenten/Jo Roets.pdf'],
            'stripped gap'      => ['Jo " Roets', 'Documenten/Jo Roets.pdf'],
        ];
    }

    /** @dataProvider editorNames */
    public function test_an_editors_file_name_is_made_safe(string $label, string $entry): void
    {
        $zip = $this->build([$this->collection('Documenten', [$this->entry($label, $this->asset('dossier.pdf'))])]);

        $this->assertSame([$entry], $this->names($zip));
    }

    public function test_a_folder_name_is_made_safe(): void
    {
        $zip = $this->build([
            $this->collection("Foto's", [$this->entry('koe.jpg', $this->asset('koe.jpg'))]),
            $this->collection('../Logos', [$this->entry('Laika', $this->asset('laika.png'))]),
        ]);

        $this->assertSame(["Foto's/koe.jpg", 'Logos/Laika.png'], $this->names($zip));
    }

    /** The README speaks the kit's language: its caller's words. */
    public function test_the_empty_kit_holds_a_readme(): void
    {
        $zip = $this->build([], 'Ce dossier de presse ne contient pas encore de fichiers.');

        $this->assertSame(['README.txt'], $this->names($zip));
        $readme = $zip->getFromName('README.txt');
        $this->assertSame("Ce dossier de presse ne contient pas encore de fichiers.\n", $readme);
    }

    /** Media is stored as it is — deflating a jpeg or a pdf again costs time and saves nothing; text is deflated. */
    public function test_compressed_media_is_stored_and_text_deflated(): void
    {
        $text = str_repeat('Een voorstelling over kijken. ', 40);
        $files = [
            'koe.jpg', 'stal.JPEG', 'laika.png', 'gif.gif', 'web.webp',
            'dossier.pdf', 'oud.zip', 'lied.mp3', 'film.mp4',
        ];
        $media = [];

        foreach ($files as $file) {
            $media[] = $this->entry($file, $this->asset($file, $text), 'Foto: An Smet ' . $text);
        }

        $zip = $this->build([
            $this->collection('Fotos', $media),
            $this->collection('Documenten', [$this->entry('Rider', $this->asset('rider.txt', $text))]),
        ]);
        $empty = $this->build([], $text);

        foreach ($files as $file) {
            $method = $zip->statName("Fotos/$file")['comp_method'] ?? null;
            $this->assertSame(\ZipArchive::CM_STORE, $method, "$file is stored");
        }

        $deflated = [[$zip, 'Fotos/credits.txt'], [$zip, 'Documenten/Rider.txt'], [$empty, 'README.txt']];
        foreach ($deflated as [$archive, $name]) {
            $method = $archive->statName($name)['comp_method'] ?? null;
            $this->assertSame(\ZipArchive::CM_DEFLATE, $method, "$name is deflated");
        }
    }

    public function test_no_entry_is_zero_bytes(): void
    {
        $photo = $this->entry('koe.jpg', $this->asset('koe.jpg'), 'Foto: An Smet');
        $zip = $this->build([$this->collection('Fotos', [$photo])]);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = (array) $zip->statIndex($i);
            $this->assertGreaterThan(0, (int) $stat['size'], "{$stat['name']} is empty");
        }
    }

    public function test_a_destination_that_cannot_be_opened_fails_closed(): void
    {
        $this->assertNull(KitArchiveBuilder::build($this->destination . '/missing/kit.zip', [], self::README));
        $this->assertSame([], glob($this->destination . '/*') ?: [], 'and leaves nothing on disk');
    }

    /** The archive's own name reaches Content-Disposition: no CR, LF or quote may survive into it. */
    public function test_the_archive_name_cannot_split_or_close_a_header(): void
    {
        $name = (new ArchiveNameSanitizer())->sanitize("Wat \"de\" koe\r\nSet-Cookie: x=1.zip");

        $this->assertSame('Wat de koe Set-Cookie x1.zip', $name);
        $plain = (new ArchiveNameSanitizer())->sanitize('Ce que la vache a vu.zip');
        $this->assertSame('Ce que la vache a vu.zip', $plain);
    }

    /** A title or a label keeps a slash as a word; only a file's path is cut to its name. */
    public function test_a_slash_in_a_name_is_a_word_and_a_path_is_its_name(): void
    {
        $this->assertSame('AC - DC.zip', (new ArchiveNameSanitizer())->sanitize('AC/DC.zip'));
        $this->assertSame('AC - DC.zip', (new ArchiveNameSanitizer())->sanitize('AC\\DC.zip'));
        $this->assertSame('passwd', (new ArchiveNameSanitizer())->fromPath('../../etc/passwd'));
        $this->assertSame('system', (new ArchiveNameSanitizer())->fromPath('C:\\Windows\\system'));
    }

    /**
     * A long title would push Content-Disposition past a proxy's buffer (a 502), and an entry over 255
     * bytes fails to extract: each stem is cut at 120 bytes, on a character boundary, its extension kept.
     */
    public function test_a_long_name_is_cut_on_a_character(): void
    {
        // A one-byte letter first, so a cut at 120 bytes falls inside a two-byte é.
        $long = 'H' . str_repeat('é', 299);

        $archive = (new ArchiveNameSanitizer())->sanitize($long . '.zip');
        $zip = $this->build([$this->collection('Documenten', [
            $this->entry($long, $this->asset('one.pdf')),
            $this->entry($long, $this->asset('two.pdf')),
        ])]);
        [$first, $second] = $this->names($zip);

        $cut = 'H' . str_repeat('é', 59);
        $this->assertSame("$cut.zip", $archive, 'the archive name: 119 bytes, the é the cut fell in dropped');
        $this->assertSame("Documenten/$cut.pdf", $first, 'an entry, its extension kept');
        $suffixed = 'Documenten/H' . str_repeat('é', 58) . '-1.pdf';
        $this->assertSame($suffixed, $second, 'its namesake, the suffix within the cap');

        foreach ([$archive, $first, $second] as $name) {
            $stem = pathinfo(basename($name), PATHINFO_FILENAME);
            $this->assertLessThanOrEqual(120, strlen($stem), "$name: a stem of at most 120 bytes");
            $this->assertTrue(mb_check_encoding($name, 'UTF-8'), "$name is valid UTF-8");
        }
    }

    /** @param list<array{label: string, entries: list<array<string, string>>}> $collections */
    private function build(array $collections, string $readme = self::README): \ZipArchive
    {
        $result = KitArchiveBuilder::build($this->path(), $collections, $readme);

        $this->assertIsArray($result, 'a buildable kit returns the zip it wrote');

        return $this->open($result['path']);
    }

    private function open(string $path): \ZipArchive
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'the zip opens');

        return $zip;
    }

    /** An empty destination file, as tempnam() hands the service one. */
    private function path(): string
    {
        $path = $this->destination . '/kit-' . ++$this->built . '.zip';
        $this->assertTrue(touch($path));

        return $path;
    }

    /** @return list<string> */
    private function names(\ZipArchive $zip): array
    {
        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }

        return $names;
    }

    /**
     * @param list<array<string, string>> $entries
     * @return array{label: string, entries: list<array<string, string>>}
     */
    private function collection(string $label, array $entries): array
    {
        return ['label' => $label, 'entries' => $entries];
    }

    /**
     * @param array{path: string, bytes: string} $asset
     * @return array<string, string>
     */
    private function entry(string $label, array $asset, string $credit = '', string $ref = '1'): array
    {
        return ['ref' => $ref, 'label' => $label, 'file' => $asset['path'], 'credit' => $credit];
    }

    /** @return array{path: string, bytes: string} */
    private function asset(string $name, ?string $bytes = null): array
    {
        $path = $this->assets . '/' . $name;
        $bytes ??= 'KIT-' . strtoupper($name) . '-BYTES';
        $this->assertNotFalse(file_put_contents($path, $bytes));

        return ['path' => $path, 'bytes' => $bytes];
    }
}
