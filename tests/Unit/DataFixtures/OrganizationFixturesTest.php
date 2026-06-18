<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures;

use App\DataFixtures\OrganizationFixtures;
use App\Entity\Organization;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

final class OrganizationFixturesTest extends TestCase
{
    // Tests that load() persists the three baseline kommuner with the expected names and default framework.
    public function testLoadPersistsThreeBaselineOrganisations(): void
    {
        $persisted = $this->captureLoad();

        self::assertCount(3, $persisted);

        $names = array_map(
            static fn (Organization $o) => $o->getName(),
            $persisted,
        );
        self::assertSame(
            ['Aarhus Kommune', 'Aalborg Kommune', 'Odense Kommune'],
            $names,
        );

        foreach ($persisted as $organization) {
            self::assertSame('openwebui', $organization->getDefaultFramework());
            self::assertNotEmpty($organization->getEmails(), 'every fixture organisation has at least one email');
        }
    }

    // Ensures load() emits the same organisations on every run (no randomness).
    public function testLoadIsDeterministic(): void
    {
        $first = array_map(
            static fn (Organization $o) => $o->getName(),
            $this->captureLoad(),
        );
        $second = array_map(
            static fn (Organization $o) => $o->getName(),
            $this->captureLoad(),
        );

        self::assertSame($first, $second);
    }

    /**
     * @return list<Organization>
     */
    private function captureLoad(): array
    {
        $captured = [];
        $manager = $this->createMock(ObjectManager::class);
        $manager->method('persist')->willReturnCallback(function (object $entity) use (&$captured): void {
            \assert($entity instanceof Organization);
            $captured[] = $entity;
        });
        $manager->expects(self::once())->method('flush');

        (new OrganizationFixtures())->load($manager);

        return $captured;
    }
}
