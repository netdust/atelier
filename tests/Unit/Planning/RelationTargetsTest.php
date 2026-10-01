<?php

declare(strict_types=1);

namespace AtelierTests\Unit\Planning;

use Atelier\Models\PersonRepository;
use Atelier\Planning\AbsenceRepository;
use Atelier\Planning\ActivityRepository;
use Atelier\Planning\AssignmentRepository;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

if (!class_exists('WP_Error')) {
    require_once __DIR__ . '/../Support/wp-error-stub.php';
}

/**
 * Core's relation sanitizer stores any positive id; the repository refuses
 * one that is not a record of the relation's own type, before anything is written.
 */
final class RelationTargetsTest extends TestCase
{
    /** id => post type, as WordPress would answer */
    private const TYPES = [5 => 'person', 6 => 'production', 7 => 'performance', 8 => 'organisation', 9 => 'activity'];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('get_post_type')->alias(static fn ($id) => self::TYPES[(int) $id] ?? false);
        Functions\expect('ntdst_data')->never();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /** @return array<string, array{string, int, string}> */
    public static function activityEdges(): array
    {
        return [
            'production is a person'    => ['production', 5, 'not_a_production'],
            'performance is a production' => ['performance', 6, 'not_a_performance'],
            'location is a person'      => ['location', 5, 'not_a_location'],
            'production does not exist' => ['production', 404, 'not_a_production'],
        ];
    }

    /** @dataProvider activityEdges */
    public function test_an_activity_refuses_a_relation_of_another_type(string $field, int $id, string $code): void
    {
        $activities = new ActivityRepository();

        $this->assertSame($code, $activities->create(['title' => 'Montage', $field => $id])->get_error_code());
        $this->assertSame($code, $activities->update(12, [$field => [$id]])->get_error_code());
    }

    public function test_an_absence_refuses_a_person_of_another_type(): void
    {
        $refused = (new AbsenceRepository())->create(['person' => 8, 'from' => '2026-05-10', 'to' => '2026-05-30']);

        $this->assertSame('not_a_person', $refused->get_error_code());
    }

    public function test_an_assignment_refuses_a_person_of_another_type(): void
    {
        $assignments = new AssignmentRepository(new PersonRepository(), new ActivityRepository());

        $this->assertSame('not_a_person', $assignments->create(['activity' => 9, 'person' => 6])->get_error_code());
    }
}
