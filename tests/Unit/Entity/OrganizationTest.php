<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Organization;
use PHPUnit\Framework\TestCase;

final class OrganizationTest extends TestCase
{
    // Tests that the constructor stores name and default framework and re-indexes emails as a list.
    public function testConstructorPopulatesFieldsAndReindexesEmails(): void
    {
        $organization = new Organization(
            'Aarhus Kommune',
            [5 => 'aarhus.dk', 3 => 'aak.dk'],
            'openwebui',
        );

        self::assertNull($organization->getId());
        self::assertSame('Aarhus Kommune', $organization->getName());
        self::assertSame(['aarhus.dk', 'aak.dk'], $organization->getEmails());
        self::assertSame('openwebui', $organization->getDefaultFramework());
    }

    // Tests that each setter updates the underlying field and returns the entity for chaining.
    public function testSettersMutateAndReturnStatic(): void
    {
        $organization = new Organization('Aarhus Kommune', ['aarhus.dk'], 'openwebui');

        self::assertSame($organization, $organization->setName('Aalborg Kommune'));
        self::assertSame('Aalborg Kommune', $organization->getName());

        self::assertSame($organization, $organization->setEmails([9 => 'aalborg.dk', 1 => 'aalborgkommune.dk']));
        self::assertSame(['aalborg.dk', 'aalborgkommune.dk'], $organization->getEmails());

        self::assertSame($organization, $organization->setDefaultFramework('langflow'));
        self::assertSame('langflow', $organization->getDefaultFramework());
    }
}
