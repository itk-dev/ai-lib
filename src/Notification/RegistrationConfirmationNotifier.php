<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\User;
use App\Mail\EmailTemplateRenderer;
use App\Settings\SettingsManager;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Sends a confirmation email to a newly-registered user.
 *
 * Subject + body come from {@see SettingsManager}, so an admin
 * can rewrite the copy through `/admin/settings/email` without a
 * redeploy. Available `%token%` placeholders the admin can use
 * in both subject and body:
 *
 * - `%name%`        — the user's display name
 * - `%email%`       — the user's e-mail address
 * - `%brand_name%`  — current brand identity
 */
class RegistrationConfirmationNotifier
{
    /**
     * @param MailerInterface       $mailer      Symfony Mailer used to dispatch the message
     * @param SettingsManager       $settings    typed accessor for the admin-editable templates
     * @param EmailTemplateRenderer $renderer    resolves the Markdown template into subject + html + text
     * @param string                $fromAddress `From` address, sourced from the `MAILER_FROM` env var
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly SettingsManager $settings,
        private readonly EmailTemplateRenderer $renderer,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $fromAddress,
    ) {
    }

    /**
     * Dispatch the "thanks, awaiting approval" message to a registered user.
     *
     * @param User $user the user the registration was created for
     */
    public function confirmRegistration(User $user): void
    {
        $rendered = $this->renderer->render(
            $this->settings->getRegistrationConfirmationSubject(),
            $this->settings->getRegistrationConfirmationBody(),
            [
                'name' => $user->getName(),
                'email' => (string) $user->getEmail(),
                'brand_name' => $this->settings->getBrandName(),
            ],
        );

        $email = (new TemplatedEmail())
            ->from(Address::create($this->fromAddress))
            ->to(Address::create((string) $user->getEmail()))
            ->subject($rendered->subject)
            ->htmlTemplate('email/registration/confirmation.html.twig')
            ->textTemplate('email/registration/confirmation.txt.twig')
            ->context(['bodyHtml' => $rendered->bodyHtml, 'bodyText' => $rendered->bodyText]);

        $this->mailer->send($email);
    }
}
