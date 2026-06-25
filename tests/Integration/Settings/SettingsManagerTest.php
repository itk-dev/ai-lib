<?php

declare(strict_types=1);

namespace App\Tests\Integration\Settings;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use App\Settings\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * End-to-end coverage of {@see SettingsManager} against the real
 * `setting` table.
 *
 * Mutations are rolled back per-test by
 * `dama/doctrine-test-bundle`, so each test starts from an empty
 * `setting` table.
 */
final class SettingsManagerTest extends KernelTestCase
{
    // Verifies getAdminRecipient returns null when the row hasn't been written yet.
    public function testGetAdminRecipientReturnsNullWhenUnset(): void
    {
        $manager = self::getContainer()->get(SettingsManager::class);

        self::assertNull($manager->getAdminRecipient());
    }

    // Tests that setAdminRecipient inserts a new row when none exists and the value round-trips through the repository.
    public function testSetAdminRecipientInsertsNewRow(): void
    {
        $manager = self::getContainer()->get(SettingsManager::class);

        $manager->setAdminRecipient('ops@example.test');

        self::assertSame('ops@example.test', $manager->getAdminRecipient());

        $repository = self::getContainer()->get(SettingRepository::class);
        $row = $repository->findOneByName(SettingsManager::ADMIN_RECIPIENT);
        self::assertInstanceOf(Setting::class, $row);
        self::assertSame(SettingsManager::ADMIN_RECIPIENT, $row->getName());
        self::assertSame('ops@example.test', $row->getValue());
        self::assertNotNull($row->getId(), 'persisted row must carry an auto-generated id');
    }

    // Verifies the second call updates the existing row in place rather than inserting a duplicate.
    public function testSetAdminRecipientUpdatesExistingRow(): void
    {
        $manager = self::getContainer()->get(SettingsManager::class);

        $manager->setAdminRecipient('first@example.test');
        $manager->setAdminRecipient('second@example.test');

        self::assertSame('second@example.test', $manager->getAdminRecipient());

        $rowCount = self::getContainer()
            ->get(EntityManagerInterface::class)
            ->getRepository(Setting::class)
            ->count(['name' => SettingsManager::ADMIN_RECIPIENT]);
        self::assertSame(1, $rowCount, 'must reuse the row instead of inserting a duplicate');
    }

    // Ensures passing null clears the value (representing "intentionally unset" without deleting the row).
    public function testSetAdminRecipientNullClearsTheValue(): void
    {
        $manager = self::getContainer()->get(SettingsManager::class);

        $manager->setAdminRecipient('ops@example.test');
        $manager->setAdminRecipient(null);

        self::assertNull($manager->getAdminRecipient());
    }

    // Verifies applyAdminRecipient accepts a null payload as "clear the setting" without going through the trim path.
    public function testApplyAdminRecipientAcceptsNullAsAClear(): void
    {
        $manager = self::getContainer()->get(SettingsManager::class);
        $manager->setAdminRecipient('ops@example.test');

        self::assertTrue($manager->applyAdminRecipient(null));
        self::assertNull($manager->getAdminRecipient());
    }
}
