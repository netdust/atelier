<?php

declare(strict_types=1);

namespace AtelierTests\Unit\Production;

use Atelier\Production\ProductionModel;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Production language and audience are fixed vocabularies like the stage:
 * seeded with their terms and locked, so editors pick and never invent.
 */
final class ProductionVocabulariesTest extends TestCase
{
    private array $registered = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('add_filter')->justReturn(true);
        Functions\when('add_action')->justReturn(true);

        $registry = new class ($this->registered) {
            public function __construct(private array &$sink)
            {
            }

            public function register(string $name, array $args): void
            {
                $this->sink[$name] = $args;
            }
        };
        Functions\when('ntdst_data')->justReturn($registry);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_language_and_audience_are_seeded_and_locked(): void
    {
        ProductionModel::register([
            'slug'    => 'productions',
            'archive' => 'productions',
            'labels'  => ['name' => 'Productions', 'singular_name' => 'Production'],
        ]);

        $taxonomies = $this->registered['production']['taxonomies'];

        $this->assertSame(
            ['nl' => 'Dutch', 'fr' => 'French', 'en' => 'English', 'non-verbal' => 'Non-verbal'],
            $taxonomies['production_language']['terms'],
        );
        $this->assertSame(
            ['public' => 'Public', 'school' => 'School', 'family' => 'Family'],
            $taxonomies['production_audience']['terms'],
        );

        foreach (['production_language', 'production_audience'] as $taxonomy) {
            $this->assertSame('do_not_allow', $taxonomies[$taxonomy]['capabilities']['edit_terms'], $taxonomy);
            $this->assertSame('edit_posts', $taxonomies[$taxonomy]['capabilities']['assign_terms'], $taxonomy);
        }
    }
}
