<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures;

use App\DataFixtures\SettingFixtures;
use App\Settings\SettingsManager;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage of {@see SettingFixtures}.
 *
 * The integration bootstrap intentionally does NOT load this
 * fixture (existing `SettingsManagerTest` cases assert an empty
 * `setting` table at test start). Covering `load()` here instead
 * keeps the 100 % coverage gate intact without leaking
 * fixture-driven rows into the integration suite.
 */
final class SettingFixturesTest extends TestCase
{
    // Verifies load() calls one setter per SettingsManager key currently defined.
    public function testLoadCallsOneSetterPerKey(): void
    {
        $settings = $this->createMock(SettingsManager::class);
        $settings->expects(self::once())->method('setAdminRecipient');
        $settings->expects(self::once())->method('setBrandName');
        $settings->expects(self::once())->method('setBrandTagline');
        $settings->expects(self::once())->method('setBrandInitials');
        $settings->expects(self::once())->method('setHeroText');
        $settings->expects(self::once())->method('setAdminNotificationSubject');
        $settings->expects(self::once())->method('setAdminNotificationBody');
        $settings->expects(self::once())->method('setRegistrationConfirmationSubject');
        $settings->expects(self::once())->method('setRegistrationConfirmationBody');

        $fixture = new SettingFixtures($settings);

        $fixture->load($this->createMock(ObjectManager::class));
    }
}
