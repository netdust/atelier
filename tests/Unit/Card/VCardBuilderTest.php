<?php

declare(strict_types=1);

namespace AtelierTests\Unit\Card;

use Atelier\Card\VCardBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The card's vCards, ported from daan's VCardBuilderTest, VCardBuildTest,
 * VCardEscapeTest, VCardEscapeControlCharTest and VCardFoldTest. Every value
 * is editor-authored, so none may break, add or restructure a vCard line.
 * N: follows R-1: `Family;Given;;;`, a one-word name all family.
 */
final class VCardBuilderTest extends TestCase
{
    private const EM_DASH = "\u{2014}";
    private const E_DIAERESIS = "\u{00EB}";

    private const PERSON = [
        'name'    => 'Jan Van den Broeck',
        'given'   => 'Jan',
        'family'  => 'Van den Broeck',
        'title'   => 'zakelijk leider',
        'email'   => 'jan@laika.be',
        'phone'   => '+32 (0)3 123-45-67',
        'company' => ['name' => 'Laika', 'url' => 'https://laika.be'],
    ];

    private const MONONYM = ['name' => 'Fien', 'given' => '', 'family' => 'Fien'] + self::PERSON;

    // --- build(): the full record (daan VCardBuildTest) ---

    public function test_the_full_record_is_exactly_these_bytes(): void
    {
        $expected = "BEGIN:VCARD\r\n"
            . "VERSION:4.0\r\n"
            . "FN:Jan Van den Broeck\r\n"
            . "N:Van den Broeck;Jan;;;\r\n"
            . "TITLE:zakelijk leider\r\n"
            . "ORG:Laika\r\n"
            . "EMAIL:jan@laika.be\r\n"
            . "TEL:+32031234567\r\n"
            . "URL:https://laika.be\r\n"
            . "END:VCARD\r\n";

        $this->assertSame($expected, VCardBuilder::build(self::PERSON));
        $this->assertSame($expected, VCardBuilder::build(self::PERSON, false));
    }

    public function test_a_wider_source_map_publishes_nothing_more(): void
    {
        $wide = self::PERSON + [
            'card_email' => 'marker-card-email@x.be',
            'dietary'    => 'Marker-dietary-8c22',
            'wp_user'    => 'Marker-wpuser-3d10',
            'share_url'  => 'https://marker-share.example',
            'photo'      => 'https://marker-photo.example/p.jpg',
        ];

        $output = VCardBuilder::build($wide);

        $this->assertSame(VCardBuilder::build(self::PERSON), $output);
        foreach (['marker-card-email', 'Marker-dietary', 'Marker-wpuser', 'marker-share', 'marker-photo'] as $marker) {
            $this->assertStringNotContainsString($marker, $output);
        }
    }

    public function test_n_splits_given_and_family(): void
    {
        $this->assertSame(['N:Van den Broeck;Jan;;;'], self::linesOf(VCardBuilder::build(self::PERSON), 'N'));
        $this->assertSame(['N:Fien;;;;'], self::linesOf(VCardBuilder::build(self::MONONYM), 'N'));
    }

    public function test_n_needs_a_name_part_and_carries_a_given_name_alone(): void
    {
        $nameless = VCardBuilder::build(['name' => '', 'given' => '', 'family' => ''] + self::PERSON);
        $givenOnly = VCardBuilder::build(['given' => 'Jan', 'family' => ''] + self::PERSON);

        $this->assertDoesNotMatchRegularExpression('/(?:^|\r\n)N:/', $nameless);
        $this->assertStringContainsString("\r\nN:;Jan;;;\r\n", $givenOnly);
    }

    /** @dataProvider modes */
    public function test_a_minimal_source_is_a_valid_record_with_no_empty_line(bool $compact, string $version): void
    {
        $output = VCardBuilder::build([
            'name'    => 'Fien',
            'given'   => '',
            'family'  => 'Fien',
            'phone'   => '+ ( ) -',
            'company' => 'not-an-array',
        ], $compact);
        $props = $this->assertWellFormed($output, $version);

        $this->assertSame('Fien', $props['FN'][0] ?? null);
        foreach (self::contentLines($output) as $line) {
            $this->assertDoesNotMatchRegularExpression('/^[A-Za-z0-9-]+(?:;[^:]*)?:\s*$/', $line);
        }
        $this->assertArrayNotHasKey('ORG', $props);
        $this->assertArrayNotHasKey('TEL', $props);
    }

    /** @return array<string, array{bool, string}> */
    public static function modes(): array
    {
        return [
            'full'    => [false, '4.0'],
            'compact' => [true, '3.0'],
        ];
    }

    public function test_a_non_scalar_value_is_dropped_not_coerced(): void
    {
        $output = VCardBuilder::build(['title' => ['x'], 'email' => new \stdClass()] + self::PERSON);

        $this->assertStringNotContainsString('Array', $output);
        $this->assertArrayNotHasKey('TITLE', self::parse($output));
        $this->assertArrayNotHasKey('EMAIL', self::parse($output));
    }

    public function test_values_are_trimmed_and_a_blank_one_emits_no_line(): void
    {
        $record = VCardBuilder::build([
            'title'   => " zakelijk leider\n",
            'email'   => '   ',
            'company' => ['name' => "Laika \n", 'url' => ' https://laika.be '],
        ] + self::PERSON);

        $this->assertStringContainsString("\r\nTITLE:zakelijk leider\r\n", $record);
        $this->assertStringContainsString("\r\nORG:Laika\r\n", $record);
        $this->assertStringContainsString("\r\nURL:https://laika.be\r\n", $record);
        $this->assertStringNotContainsString('EMAIL:', $record);
    }

    public function test_a_semicolon_in_the_company_is_escaped(): void
    {
        $source = self::PERSON;
        $source['company'] = ['name' => 'Laika; [PIAS] Recordings', 'url' => 'https://laika.be'];

        $org = $this->assertWellFormed(VCardBuilder::build($source), '4.0')['ORG'][0];

        $this->assertStringContainsString('\\;', $org);
        $this->assertSame('Laika; [PIAS] Recordings', self::unescape($org));
        $this->assertCount(1, self::components($org), 'ORG stays one component');
    }

    public function test_crlf_in_a_value_cannot_split_the_record_or_inject_a_second(): void
    {
        $source = self::PERSON;
        $source['title'] = "leider\r\nEMAIL:attacker@evil.tld\r\nEND:VCARD\r\nBEGIN:VCARD";

        $output = VCardBuilder::build($source);

        $this->assertSame(1, preg_match_all('/(?:^|\r\n)BEGIN:VCARD(?=\r\n)/', $output));
        $this->assertSame(1, preg_match_all('/(?:^|\r\n)END:VCARD(?=\r\n|$)/', $output));
        $this->assertSame(['jan@laika.be'], self::parse($output)['EMAIL']);
    }

    public function test_a_semicolon_in_a_name_cannot_add_a_component(): void
    {
        $source = ['name' => 'Jan;Evil', 'given' => 'Jan;Evil', 'family' => 'Van;den;Broeck'] + self::PERSON;

        $n = self::components($this->assertWellFormed(VCardBuilder::build($source), '4.0')['N'][0]);

        $this->assertCount(5, $n);
        $this->assertSame('Van;den;Broeck', self::unescape($n[0]));
        $this->assertSame('Jan;Evil', self::unescape($n[1]));
    }

    // --- build(): the compact record (daan VCardBuilderTest) ---

    public function test_compact_mode_is_one_v3_record_in_the_prototype_order(): void
    {
        $lines = self::unfoldedLines(VCardBuilder::build(self::MONONYM, true));

        $this->assertSame([
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:Fien;;;;',
            'FN:Fien',
            'ORG:Laika',
            'TITLE:zakelijk leider',
            'EMAIL:jan@laika.be',
            'TEL:+32031234567',
            'URL:https://laika.be',
            'END:VCARD',
        ], $lines);
    }

    public function test_a_field_cannot_forge_extra_properties_in_compact_mode(): void
    {
        $source = [
            'title' => "Booking\r\nTEL:+666",
            'name'  => "Fien;\nURL:https://evil.tld\r\nEND:VCARD\r\nBEGIN:VCARD",
        ] + self::MONONYM;

        $lines = self::unfoldedLines(VCardBuilder::build($source, true));

        $this->assertCount(1, self::property($lines, 'FN'));
        $this->assertCount(1, self::property($lines, 'N'));
        $this->assertCount(1, self::property($lines, 'TITLE'));
        $this->assertSame(['TEL:+32031234567'], self::property($lines, 'TEL'));
        $this->assertSame(['URL:https://laika.be'], self::property($lines, 'URL'));
        $this->assertCount(1, self::property($lines, 'BEGIN'));
        $this->assertCount(1, self::property($lines, 'END'));
        $this->assertStringContainsString('Fien\\;', self::property($lines, 'FN')[0]);
    }

    public function test_tel_holds_only_digits_and_a_leading_plus(): void
    {
        $tel = self::property(self::unfoldedLines(VCardBuilder::build(self::PERSON, true)), 'TEL');

        $this->assertSame(['TEL:+32031234567'], $tel);
        $inner = VCardBuilder::build(['phone' => '03 + 123 +45'] + self::PERSON, true);
        $this->assertSame(['TEL:0312345'], self::linesOf($inner, 'TEL'));
    }

    public function test_dialable_keeps_digits_and_only_a_leading_plus(): void
    {
        $this->assertSame('+32031234567', VCardBuilder::dialable('+32 (0)3 123-45-67'));
        $this->assertSame('0312345', VCardBuilder::dialable('03 + 123 +45'));
        $this->assertSame('', VCardBuilder::dialable('+ ( ) -'));
    }

    public function test_compact_mode_folds_long_lines_on_octets(): void
    {
        $record = VCardBuilder::build(['title' => str_repeat('é', 60)] + self::PERSON, true);

        foreach (explode("\r\n", $record) as $physical) {
            $this->assertLessThanOrEqual(75, strlen($physical));
        }
        $this->assertSame(['TITLE:' . str_repeat('é', 60)], self::property(self::unfoldedLines($record), 'TITLE'));
    }

    // --- escapeValue() (daan VCardEscapeTest, VCardEscapeControlCharTest) ---

    public function test_each_special_is_escaped_exactly_once(): void
    {
        $this->assertSame('a\\\\b', VCardBuilder::escapeValue('a\\b'));
        $this->assertSame('a\\;b', VCardBuilder::escapeValue('a;b'));
        $this->assertSame('a\\,b', VCardBuilder::escapeValue('a,b'));
        $this->assertSame(
            'Heavenhotel\\; [PIAS] Recordings\\, Belgium \\\\ EU',
            VCardBuilder::escapeValue('Heavenhotel; [PIAS] Recordings, Belgium \\ EU'),
        );
    }

    public function test_no_terminator_survives_next_to_any_special(): void
    {
        $adversarial = [
            "end\\\r\nBEGIN:VCARD",
            "end\r\n\\BEGIN:VCARD",
            "a;\r\n;b",
            "a,\r\n,b",
            "already\\nliteral\r\nreal",
            "a\\b;c,d\re\nf\r\ng",
            "end\\\\\nBEGIN:VCARD",
        ];

        foreach ($adversarial as $input) {
            $result = VCardBuilder::escapeValue($input);
            $this->assertNotSame('', $result);
            $this->assertDoesNotMatchRegularExpression("/[\r\n]/", $result);
        }
    }

    public function test_the_newline_escape_is_single_not_double(): void
    {
        $result = VCardBuilder::escapeValue("Line one\r\nLine two");

        $this->assertStringNotContainsString('\\\\n', $result);
        $this->assertSame('Line one\\nLine two', $result);
    }

    public function test_raw_control_bytes_are_stripped(): void
    {
        $result = VCardBuilder::escapeValue("bio\x00with\x07control\x1Fbytes");

        $this->assertSame('biowithcontrolbytes', $result);
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $result);
    }

    public function test_tab_survives_and_specials_beside_a_stripped_byte_escape_once(): void
    {
        $this->assertSame("a\tb", VCardBuilder::escapeValue("a\tb"));
        $this->assertSame('a\\;b\\,c\\\\d', VCardBuilder::escapeValue("a\x00;b\x00,c\x00\\d"));
    }

    // --- foldLine() (daan VCardFoldTest) ---

    public function test_75_octets_is_not_folded_and_76_is(): void
    {
        $this->assertSame('FN:Fien', VCardBuilder::foldLine('FN:Fien'));

        $at = 'NOTE:' . str_repeat('x', 70);
        $over = 'NOTE:' . str_repeat('x', 71);
        $this->assertSame(75, strlen($at));
        $this->assertSame($at, VCardBuilder::foldLine($at));
        $this->assertStringContainsString("\r\n", VCardBuilder::foldLine($over));
        $this->assertSame($over, self::unfold(VCardBuilder::foldLine($over)));
    }

    public function test_a_fold_fills_each_physical_line_to_75_octets(): void
    {
        $folded = VCardBuilder::foldLine('NOTE:' . str_repeat('x', 200));

        $this->assertSame([75, 75, 57], array_map('strlen', explode("\r\n", $folded)));
    }

    public function test_continuation_lines_start_with_exactly_one_space(): void
    {
        $lines = explode("\r\n", VCardBuilder::foldLine('NOTE:' . str_repeat('Belgian singer-songwriter. ', 12)));

        $this->assertGreaterThan(1, count($lines));
        $this->assertStringStartsNotWith(' ', $lines[0]);
        foreach (array_slice($lines, 1) as $physical) {
            $this->assertSame(' ', substr($physical, 0, 1));
            $this->assertNotContains(substr($physical, 1, 1), [' ', "\t", '']);
        }
    }

    public function test_fold_is_utf8_safe_at_every_offset(): void
    {
        for ($pad = 60; $pad <= 90; $pad++) {
            $line = str_repeat('a', $pad) . self::EM_DASH . str_repeat('b', 40);
            $folded = VCardBuilder::foldLine($line);

            foreach (explode("\r\n", $folded) as $physical) {
                $this->assertTrue(mb_check_encoding($physical, 'UTF-8'), "pad=$pad");
            }
            $this->assertSame($line, self::unfold($folded), "pad=$pad");
        }
    }

    public function test_folding_measures_octets_not_characters(): void
    {
        foreach ([str_repeat(self::EM_DASH, 40), str_repeat(self::E_DIAERESIS, 60)] as $line) {
            $this->assertSame(120, strlen($line));
            $folded = VCardBuilder::foldLine($line);
            $this->assertStringContainsString("\r\n", $folded);
            $this->assertSame($line, self::unfold($folded));
        }
    }

    public function test_every_fixture_round_trips_within_75_octets_as_valid_utf8(): void
    {
        $fixtures = [
            'FN:Fien',
            'NOTE:' . str_repeat('x', 70),
            'NOTE:' . str_repeat('x', 71),
            'NOTE:' . str_repeat('Belgian singer-songwriter. ', 12),
            'NOTE:Jan ' . self::EM_DASH . ' zakelijk leider, geb' . self::E_DIAERESIS . 'nspireerd; '
                . 'Laika / [PIAS] Recordings, Belgi' . self::E_DIAERESIS . '.',
            str_repeat(self::EM_DASH, 40),
            str_repeat(self::E_DIAERESIS, 60),
            str_repeat('ab' . self::EM_DASH, 40),
            self::EM_DASH,
            '',
            'NOTE:Heavenhotel\\; [PIAS] Recordings\\, Belgium \\\\ EU ' . str_repeat('and more text ', 6),
        ];

        foreach ($fixtures as $i => $line) {
            $folded = VCardBuilder::foldLine($line);

            $this->assertSame($line, self::unfold($folded), "fixture $i round-trips");
            $unbroken = str_replace("\r\n ", '', $folded);
            $this->assertDoesNotMatchRegularExpression("/[\r\n]/", $unbroken, "fixture $i: only folds break lines");
            foreach (explode("\r\n", $folded) as $physical) {
                $this->assertLessThanOrEqual(75, strlen($physical), "fixture $i");
                $this->assertTrue(mb_check_encoding($physical, 'UTF-8'), "fixture $i");
            }
        }
        $this->assertSame('', VCardBuilder::foldLine(''));
    }

    // --- helpers ---

    /** @return array<string, list<string>> */
    private function assertWellFormed(string $output, string $version): array
    {
        $this->assertNotSame('', $output);
        $this->assertSame(1, preg_match_all('/(?:^|\r\n)BEGIN:VCARD(?=\r\n)/', $output));
        $this->assertSame(1, preg_match_all('/(?:^|\r\n)END:VCARD(?=\r\n|$)/', $output));
        $this->assertSame(1, preg_match_all('/(?:^|\r\n)VERSION:' . preg_quote($version, '/') . '(?=\r\n)/', $output));
        foreach (self::unfoldedLines($output) as $line) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9-]+(?:;[^:]*)?:/', $line);
        }

        return self::parse($output);
    }

    /** @return array<string, list<string>> */
    private static function parse(string $output): array
    {
        $props = [];
        foreach (self::contentLines($output) as $line) {
            [$name, $value] = explode(':', $line, 2) + [1 => ''];
            $props[strtoupper(explode(';', $name)[0])][] = $value;
        }

        return $props;
    }

    /** @return list<string> */
    private static function contentLines(string $output): array
    {
        return array_values(array_filter(
            self::unfoldedLines($output),
            static fn(string $line): bool => !in_array(
                strtoupper($line),
                ['BEGIN:VCARD', 'END:VCARD', 'VERSION:4.0', 'VERSION:3.0'],
                true,
            ),
        ));
    }

    /** @return list<string> */
    private static function unfoldedLines(string $output): array
    {
        $lines = explode("\r\n", self::unfold($output));

        return array_values(array_filter($lines, static fn(string $line): bool => $line !== ''));
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private static function property(array $lines, string $name): array
    {
        return array_values(array_filter($lines, static fn(string $line): bool => str_starts_with($line, $name . ':')));
    }

    /** @return list<string> */
    private static function linesOf(string $record, string $name): array
    {
        return self::property(self::unfoldedLines($record), $name);
    }

    private static function unfold(string $folded): string
    {
        return preg_replace("/\r\n[ \t]/", '', $folded) ?? '';
    }

    /** @return list<string> a structured value split on its unescaped `;` */
    private static function components(string $value): array
    {
        return preg_split('/(?<!\\\\)(?:\\\\\\\\)*\K;/', $value) ?: [];
    }

    private static function unescape(string $value): string
    {
        return (string) preg_replace_callback(
            '/\\\\(.)/',
            static fn(array $m): string => match ($m[1]) {
                'n', 'N' => "\n",
                '\\', ';', ',' => $m[1],
                default => $m[0],
            },
            $value,
        );
    }
}
