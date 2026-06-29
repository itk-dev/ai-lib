<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Notification\AdminRegistrationNotifier;
use App\Notification\RegistrationConfirmationNotifier;
use App\Settings\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

/**
 * End-to-end coverage of the two registration notifiers.
 *
 * Symfony's `null://null` transport (configured in `.env.test`)
 * captures every sent message in the `MessageDataCollector` so
 * `MailerAssertionsTrait` can introspect them without
 * delivering anything off-box.
 */
final class NotifierIntegrationTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    private SettingsManager $settings;
    private AdminRegistrationNotifier $adminNotifier;
    private RegistrationConfirmationNotifier $confirmationNotifier;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->settings = self::getContainer()->get(SettingsManager::class);
        $this->adminNotifier = self::getContainer()->get(AdminRegistrationNotifier::class);
        $this->confirmationNotifier = self::getContainer()->get(RegistrationConfirmationNotifier::class);
    }

    // Verifies the admin notifier sends to the configured recipient with the rendered subject and replaced %email% token.
    public function testAdminNotifierSendsToConfiguredRecipientWithRenderedSubject(): void
    {
        $this->settings->setAdminRecipient('ops@example.test');
        $this->settings->setAdminNotificationSubject('Ny bruger: %name%');
        $this->settings->setAdminNotificationBody('E-mail: %email%, godkend på %approval_url%.');

        $this->adminNotifier->notifyOfNewRegistration($this->makeUser());

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertSame('Ny bruger: Carol', $email->getSubject());
        self::assertSame('ops@example.test', $email->getTo()[0]->getAddress());
        self::assertStringContainsString('carol@example.test', $email->getTextBody() ?? '');
    }

    // Ensures the admin notifier skips the send when the recipient is unset (the registration flow stays alive).
    public function testAdminNotifierSkipsWhenRecipientIsUnset(): void
    {
        // No setAdminRecipient — leave it null.
        $this->adminNotifier->notifyOfNewRegistration($this->makeUser());

        self::assertEmailCount(0);
    }

    // Verifies the confirmation notifier sends to the registered user with the rendered template.
    public function testConfirmationNotifierSendsToRegisteredUser(): void
    {
        $this->settings->setRegistrationConfirmationSubject('Hej %name%');
        $this->settings->setRegistrationConfirmationBody('Tak for din oprettelse, %name%.');

        $this->confirmationNotifier->confirmRegistration($this->makeUser());

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertSame('Hej Carol', $email->getSubject());
        self::assertSame('carol@example.test', $email->getTo()[0]->getAddress());
        self::assertStringContainsString('Tak for din oprettelse, Carol.', $email->getTextBody() ?? '');
    }

    private function makeUser(): User
    {
        return (new User())
            ->setEmail('carol@example.test')
            ->setName('Carol')
            ->setStatus(UserStatus::Pending);
    }
}
