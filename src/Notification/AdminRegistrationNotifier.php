<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\User;
use App\Mail\EmailTemplateRenderer;
use App\Settings\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends an email to the configured moderator inbox when a new
 * user self-registers and is awaiting approval.
 *
 * Subject + body come from {@see SettingsManager}, so an admin
 * can rewrite the copy through `/admin/settings/email` without a
 * redeploy. Available `%token%` placeholders the admin can use
 * in both subject and body:
 *
 * - `%name%`        — the new user's display name
 * - `%email%`       — the new user's e-mail address
 * - `%brand_name%`  — current brand identity
 * - `%approval_url%` — absolute URL to the pending-users admin queue
 *
 * When the recipient is unset, logs a warning and returns
 * without sending.
 */
class AdminRegistrationNotifier
{
    /**
     * @param MailerInterface       $mailer        Symfony Mailer used to dispatch the message
     * @param SettingsManager       $settings      typed accessor for the admin-editable templates + recipient
     * @param EmailTemplateRenderer $renderer      resolves the Markdown template into subject + html + text
     * @param UrlGeneratorInterface $urlGenerator  absolute-URL helper for the `%approval_url%` token
     * @param LoggerInterface       $logger        receives a warning when the recipient is unset
     * @param string                $fromAddress   `From` address, sourced from the `MAILER_FROM` env var
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly SettingsManager $settings,
        private readonly EmailTemplateRenderer $renderer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $fromAddress,
    ) {
    }

    /**
     * Dispatch the admin notification for a freshly-registered user.
     *
     * Looks up the recipient via {@see SettingsManager::getAdminRecipient()}.
     * If no recipient is configured, the send is skipped with a
     * warning so the user's own registration succeeds even when
     * the moderator inbox is unset.
     *
     * @param User $user the newly-created pending user
     */
    public function notifyOfNewRegistration(User $user): void
    {
        $recipient = $this->settings->getAdminRecipient();
        if (null === $recipient) {
            $this->logger->warning('Admin recipient is unset; skipping registration notification.', [
                'user_email' => $user->getUserIdentifier(),
            ]);

            return;
        }

        $rendered = $this->renderer->render(
            $this->settings->getAdminNotificationSubject(),
            $this->settings->getAdminNotificationBody(),
            [
                'name' => $user->getName(),
                'email' => (string) $user->getEmail(),
                'brand_name' => $this->settings->getBrandName(),
                'approval_url' => $this->urlGenerator->generate(
                    'app_admin_users',
                    ['status' => 'pending'],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ],
        );

        $email = (new TemplatedEmail())
            ->from(Address::create($this->fromAddress))
            ->to(Address::create($recipient))
            ->subject($rendered->subject)
            ->htmlTemplate('email/admin/new_registration.html.twig')
            ->textTemplate('email/admin/new_registration.txt.twig')
            ->context(['bodyHtml' => $rendered->bodyHtml, 'bodyText' => $rendered->bodyText]);

        $this->mailer->send($email);
    }
}
