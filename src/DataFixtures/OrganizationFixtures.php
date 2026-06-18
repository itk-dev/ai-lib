<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Organization;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seed three baseline organisations for local development and tests.
 *
 * Hand-picked Danish kommuner that match the kommune names already used
 * by AssistantFixtures, so downstream features (autocomplete, facet
 * counts) can be wired up against consistent data once the
 * `User → Organization` relation lands.
 */
final class OrganizationFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $entries = [
            new Organization(
                name: 'Aarhus Kommune',
                emails: ['aarhus.dk'],
                defaultFramework: 'openwebui',
            ),
            new Organization(
                name: 'Aalborg Kommune',
                emails: ['aalborg.dk', 'aalborgkommune.dk'],
                defaultFramework: 'openwebui',
            ),
            new Organization(
                name: 'Odense Kommune',
                emails: ['odense.dk'],
                defaultFramework: 'openwebui',
            ),
        ];

        foreach ($entries as $organization) {
            $manager->persist($organization);
        }
        $manager->flush();
    }
}
