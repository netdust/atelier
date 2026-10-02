<?php

declare(strict_types=1);

namespace AtelierTests\Unit\Support;

use Atelier\Support\Vocabulary;
use PHPUnit\Framework\TestCase;

/**
 * A time of day is a text fact: `HH:MM` or empty, never translated, shared
 * by performances and planning activities.
 */
final class VocabularyTest extends TestCase
{
    public function test_time_accepts_23_59_and_refuses_24_00(): void
    {
        $validate = Vocabulary::time()['validate'];

        $this->assertTrue($validate('23:59'));
        $this->assertTrue($validate('00:00'));
        $this->assertTrue($validate(''));
        $this->assertIsString($validate('24:00'));
        $this->assertIsString($validate('10u30'));
        $this->assertIsString($validate('9:00'));
    }

    public function test_time_is_a_text_fact(): void
    {
        $field = Vocabulary::time();

        $this->assertSame('text', $field['type']);
        $this->assertFalse($field['translate']);
        $this->assertFalse($field['required']);
        $this->assertTrue(Vocabulary::time(true)['required']);
    }
}
