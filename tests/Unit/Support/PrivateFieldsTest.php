<?php

declare(strict_types=1);

namespace AtelierTests\Unit\Support;

use Atelier\Models\OrganisationModel;
use Atelier\Models\PersonModel;
use Atelier\Support\PrivateFields;
use PHPUnit\Framework\TestCase;

final class PrivateFieldsTest extends TestCase
{
    public function test_private_fields_are_excluded_per_model(): void
    {
        $this->assertSame(PersonModel::PRIVATE, PrivateFields::exclude([], 'person'));

        $organisation = PrivateFields::exclude(['x'], 'organisation');
        $this->assertContains('x', $organisation);
        foreach (OrganisationModel::PRIVATE as $field) {
            $this->assertContains($field, $organisation);
        }

        $this->assertSame(['x'], PrivateFields::exclude(['x'], 'production'));
    }
}
