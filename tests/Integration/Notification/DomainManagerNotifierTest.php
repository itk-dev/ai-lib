<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Notification\DomainManagerRegistrationNotifier;
use App\Settings\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

/**
 * End-to-end coverage of the domain-manager registration notifier.
 *
 * Symfony's `null://null` transport (configured in `.env.test`)
 * captures every sent message in the `MessageDataCollector` so
 * `MailerAssertionsTrait` can introspect them without delivering
 * anything off-box. The baseline `UserFixtures` seed two Approved
 * managers on `aarhus.dk` (`DOMAIN_MANAGER_EMAIL`,
 * `SECOND_DOMAIN_MANAGER_EMAIL`) and no manager on `aalborg.dk`
 * or `odense.dk`, giving the "single / multi / no manager"
 * scenarios out of the box.
 */
final class DomainManagerNotifierTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    private SettingsManager $settings;
    private DomainManagerRegistrationNotifier $notifier;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->settings = self::getContainer()->get(SettingsManager::class);
        $this->notifier = self::getContainer()->get(DomainManagerRegistrationNotifier::class);

        // Pin a deterministic subject / body per test so assertions can
        // point at the token substitution without depending on the
        // baseline SettingFixtures copy.
        $this->settings->setAdminNotificationSubject('Ny bruger: %name%');
        $this->settings->setAdminNotificationBody('E-mail: %email%, godkend på %approval_url%.');
    }

    // Verifies each approved domain manager on the user's domain receives one mail carrying the rendered subject and token substitutions.
    public function testSendsOneMailPerApprovedDomainManagerOnTheSameDomain(): void
    {
        $this->notifier->notifyOfNewRegistration($this->newUserOnDomain('newbie@aarhus.dk', 'Newbie'));

        // Aarhus fixture: manager@aarhus.dk + manager2@aarhus.dk.
        self::assertEmailCount(2);

        $messages = self::getMailerMessages();
        $recipients = array_map(
            static fn ($m): ?string => $m->getTo()[0]->getAddress(),
            $messages,
        );
        self::assertContains(UserFixtures::DOMAIN_MANAGER_EMAIL, $recipients);
        self::assertContains(UserFixtures::SECOND_DOMAIN_MANAGER_EMAIL, $recipients);

        foreach ($messages as $email) {
            self::assertSame('Ny bruger: Newbie', $email->getSubject());
            self::assertStringContainsString('newbie@aarhus.dk', $email->getTextBody() ?? '');
        }
    }

    // Ensures the notifier is a no-op when the user's domain has no approved manager — the admin recipient still receives their own mail from AdminRegistrationNotifier, so no signal is lost.
    public function testSkipsWhenNoManagerExistsOnDomain(): void
    {
        // aalborg.dk has fixture users (pending, awaiting) but no ROLE_DOMAIN_MANAGER.
        $this->notifier->notifyOfNewRegistration($this->newUserOnDomain('newbie@aalborg.dk', 'Newbie'));

        self::assertEmailCount(0);
    }

    // Verifies the notifier excludes site admins even when they share the target domain — they already receive the admin recipient's mail.
    public function testExcludesSiteAdminsOnSameDomain(): void
    {
        $this->notifier->notifyOfNewRegistration($this->newUserOnDomain('newbie@aarhus.dk', 'Newbie'));

        $recipients = array_map(
            static fn ($m): ?string => $m->getTo()[0]->getAddress(),
            self::getMailerMessages(),
        );
        self::assertNotContains(UserFixtures::ADMIN_EMAIL, $recipients);
    }

    // Ensures the notifier skips silently when the user has no resolvable email domain.
    public function testSkipsWhenUserHasNoEmail(): void
    {
        $headless = (new User())->setName('Headless');

        $this->notifier->notifyOfNewRegistration($headless);

        self::assertEmailCount(0);
    }

    /**
     * Build an unpersisted `Pending` user with a deterministic email
     * / name pair. Persistence isn't needed — the notifier only
     * reads `getEmail()`, `getName()`, and `getUserIdentifier()`.
     */
    private function newUserOnDomain(string $email, string $name): User
    {
        return (new User())
            ->setEmail($email)
            ->setName($name)
            ->setStatus(UserStatus::Pending);
    }
}
