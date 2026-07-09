<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Mail\EmailTemplateRenderer;
use App\Notification\DomainManagerRegistrationNotifier;
use App\Repository\UserRepository;
use App\Security\Roles;
use App\Settings\SettingsManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Unit-level coverage of the per-manager try/catch branch in
 * {@see DomainManagerRegistrationNotifier}.
 *
 * The integration test in
 * {@see \App\Tests\Integration\Notification\DomainManagerNotifierTest}
 * drives the happy path against the null mailer transport and the
 * real repository; this suite complements it by simulating a
 * transport failure on one manager and verifying the loop keeps
 * going for the remaining recipients — a single flaky manager
 * mailbox must not block delivery to their peers.
 */
final class DomainManagerRegistrationNotifierTest extends TestCase
{
    // Verifies a transport failure on one manager is logged and swallowed so the next manager still receives their mail.
    public function testTransportFailureOnOneManagerDoesNotAbortTheLoop(): void
    {
        $sentTo = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (\Symfony\Component\Mime\Email $email) use (&$sentTo): void {
                $recipient = $email->getTo()[0]->getAddress();
                $sentTo[] = $recipient;
                if ('flaky@aarhus.dk' === $recipient) {
                    throw new TransportException('SMTP down for flaky');
                }
            });

        $settings = $this->createMock(SettingsManager::class);
        $settings->method('getSenderAddress')->willReturn('sender@example.test');
        $settings->method('getAdminNotificationSubject')->willReturn('Ny bruger: %name%');
        $settings->method('getAdminNotificationBody')->willReturn('E-mail: %email%');
        $settings->method('getBrandName')->willReturn('Brand');

        $flaky = $this->makeManager('flaky@aarhus.dk', 'Flaky');
        $healthy = $this->makeManager('healthy@aarhus.dk', 'Healthy');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findApprovedDomainManagersForDomain')
            ->with('aarhus.dk')
            ->willReturn([$flaky, $healthy]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('domain-manager registration notification'));

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.test/admin/users?status=pending');

        $notifier = new DomainManagerRegistrationNotifier(
            $mailer,
            $settings,
            new EmailTemplateRenderer(new \League\CommonMark\CommonMarkConverter()),
            $urlGenerator,
            $userRepository,
            $logger,
        );

        $notifier->notifyOfNewRegistration($this->makeNewUser('newbie@aarhus.dk', 'Newbie'));

        self::assertSame(['flaky@aarhus.dk', 'healthy@aarhus.dk'], $sentTo);
    }

    /**
     * Build an unpersisted `Approved` `ROLE_DOMAIN_MANAGER` user for
     * the notifier's per-manager loop.
     */
    private function makeManager(string $email, string $name): User
    {
        return (new User())
            ->setEmail($email)
            ->setName($name)
            ->setRoles([Roles::DOMAIN_MANAGER])
            ->setStatus(UserStatus::Approved);
    }

    /**
     * Build the newly-confirmed user the notifier is dispatching for.
     */
    private function makeNewUser(string $email, string $name): User
    {
        return (new User())
            ->setEmail($email)
            ->setName($name)
            ->setStatus(UserStatus::Pending);
    }
}
