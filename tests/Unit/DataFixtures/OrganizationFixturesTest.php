<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures;

use App\DataFixtures\OrganizationFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Organization;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

final class OrganizationFixturesTest extends TestCase
{
    // Ensures the fixture declares UserFixtures as a dependency so users load — and creators resolve — first.
    public function testDependsOnUserFixtures(): void
    {
        self::assertSame([UserFixtures::class], (new OrganizationFixtures())->getDependencies());
    }

    // Tests that load() persists the three baseline municipalities with the expected names and default framework.
    public function testLoadPersistsThreeBaselineOrganizations(): void
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
            self::assertNotEmpty($organization->getEmailDomains(), 'every fixture organization has at least one email domain');
        }
    }

    // Ensures load() emits the same organizations on every run (no randomness).
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
