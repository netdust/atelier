<?php

declare(strict_types=1);

namespace AtelierTests\Unit\Support;

use Atelier\Support\Labels;
use PHPUnit\Framework\TestCase;

final class LabelsTest extends TestCase
{
    public function test_expand_derives_missing_labels_from_names(): void
    {
        $labels = Labels::expand([
            'name'          => 'Voorstellingen',
            'singular_name' => 'Voorstelling',
            'menu_name'     => 'Speellijst',
        ]);

        $this->assertStringContainsString('Voorstelling', $labels['add_new_item']);
        $this->assertStringContainsString('Voorstelling', $labels['edit_item']);
        $this->assertStringContainsString('Voorstellingen', $labels['all_items']);
        $this->assertSame('Speellijst', $labels['menu_name']);
    }
}
