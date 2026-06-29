<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Mail\EmailTemplateRenderer;
use App\Notification\AdminRegistrationNotifier;
use App\Notification\RegistrationConfirmationNotifier;
use App\Settings\SettingsManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Covers the "sender address is unset" guard added with the
 * promote-MAILER_FROM-to-setting work.
 *
 * Both registration notifiers resolve `From:` via
 * {@see SettingsManager::getSenderAddress()} at send time. When
 * that returns `null` (no setting row, empty MAILER_FROM env)
 * the notifier logs a warning and returns without dispatching
 * anything — the registration request stays alive.
 */
final class NotifierSkipsWhenSenderUnsetTest extends TestCase
{
    // Ensures AdminRegistrationNotifier skips the send when the sender address is null.
    public function testAdminNotifierSkipsWhenSenderUnset(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $settings = $this->createMock(SettingsManager::class);
        $settings->method('getAdminRecipient')->willReturn('ops@example.test');
        $settings->method('getSenderAddress')->willReturn(null);

        $notifier = new AdminRegistrationNotifier(
            $mailer,
            $settings,
            new EmailTemplateRenderer(new \League\CommonMark\CommonMarkConverter()),
            $this->createMock(UrlGeneratorInterface::class),
            new NullLogger(),
        );

        $notifier->notifyOfNewRegistration($this->makeUser());
    }

    // Ensures RegistrationConfirmationNotifier skips the send when the sender address is null.
    public function testConfirmationNotifierSkipsWhenSenderUnset(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $settings = $this->createMock(SettingsManager::class);
        $settings->method('getSenderAddress')->willReturn(null);

        $notifier = new RegistrationConfirmationNotifier(
            $mailer,
            $settings,
            new EmailTemplateRenderer(new \League\CommonMark\CommonMarkConverter()),
            new NullLogger(),
        );

        $notifier->confirmRegistration($this->makeUser());
    }

    private function makeUser(): User
    {
        return (new User())
            ->setEmail('carol@example.test')
            ->setName('Carol')
            ->setStatus(UserStatus::Pending);
    }
}
