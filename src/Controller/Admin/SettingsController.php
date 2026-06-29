<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\Roles;
use App\Settings\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Roles::ADMIN)]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly SettingsManager $settingsManager,
    ) {
    }

    #[Route(path: '/admin/settings', name: 'app_admin_settings', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_admin_settings_site');
    }

    #[Route(path: '/admin/settings/site', name: 'app_admin_settings_site', methods: ['GET', 'POST'])]
    public function site(Request $request): Response
    {
        $submitted = [
            'brand_name' => $this->settingsManager->getBrandName(),
            'brand_tagline' => $this->settingsManager->getBrandTagline(),
            'brand_initials' => $this->settingsManager->getBrandInitials(),
        ];

        if ('POST' === $request->getMethod()) {
            $submitted = [
                'brand_name' => (string) $request->request->get('brand_name', ''),
                'brand_tagline' => (string) $request->request->get('brand_tagline', ''),
                'brand_initials' => (string) $request->request->get('brand_initials', ''),
            ];

            if (!$this->isCsrfTokenValid('admin-settings-site', (string) $request->request->get('_token'))) {
                return $this->render('admin/settings/site.html.twig', [
                    'submitted' => $submitted,
                    'error' => 'admin.settings.error.invalid_token',
                ], new Response('', Response::HTTP_FORBIDDEN));
            }

            $this->settingsManager->applyBrandIdentity(
                $submitted['brand_name'],
                $submitted['brand_tagline'],
                $submitted['brand_initials'],
            );
            $this->addFlash('success', 'admin.settings.flash.saved');

            return $this->redirectToRoute('app_admin_settings_site');
        }

        return $this->render('admin/settings/site.html.twig', [
            'submitted' => $submitted,
            'error' => null,
        ]);
    }

    #[Route(path: '/admin/settings/email', name: 'app_admin_settings_email', methods: ['GET', 'POST'])]
    public function email(Request $request): Response
    {
        $submitted = [
            'admin_recipient' => $this->settingsManager->getAdminRecipient() ?? '',
            'sender_address' => $this->settingsManager->getSenderAddress() ?? '',
            'admin_notification_subject' => $this->settingsManager->getAdminNotificationSubject(),
            'admin_notification_body' => $this->settingsManager->getAdminNotificationBody(),
            'registration_confirmation_subject' => $this->settingsManager->getRegistrationConfirmationSubject(),
            'registration_confirmation_body' => $this->settingsManager->getRegistrationConfirmationBody(),
        ];

        if ('POST' === $request->getMethod()) {
            $submitted = [
                'admin_recipient' => (string) $request->request->get('admin_recipient', ''),
                'sender_address' => (string) $request->request->get('sender_address', ''),
                'admin_notification_subject' => (string) $request->request->get('admin_notification_subject', ''),
                'admin_notification_body' => (string) $request->request->get('admin_notification_body', ''),
                'registration_confirmation_subject' => (string) $request->request->get('registration_confirmation_subject', ''),
                'registration_confirmation_body' => (string) $request->request->get('registration_confirmation_body', ''),
            ];

            if (!$this->isCsrfTokenValid('admin-settings-email', (string) $request->request->get('_token'))) {
                return $this->render('admin/settings/email.html.twig', [
                    'submitted' => $submitted,
                    'error' => 'admin.settings.error.invalid_token',
                ], new Response('', Response::HTTP_FORBIDDEN));
            }

            if (!$this->settingsManager->applyAdminRecipient($submitted['admin_recipient'])) {
                return $this->render('admin/settings/email.html.twig', [
                    'submitted' => $submitted,
                    'error' => 'admin.settings.error.invalid_email',
                ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            if (!$this->settingsManager->applySenderAddress($submitted['sender_address'])) {
                return $this->render('admin/settings/email.html.twig', [
                    'submitted' => $submitted,
                    'error' => 'admin.settings.error.invalid_sender',
                ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $this->settingsManager->applyEmailContent(
                $submitted['admin_notification_subject'],
                $submitted['admin_notification_body'],
                $submitted['registration_confirmation_subject'],
                $submitted['registration_confirmation_body'],
            );

            $this->addFlash('success', 'admin.settings.flash.saved');

            return $this->redirectToRoute('app_admin_settings_email');
        }

        return $this->render('admin/settings/email.html.twig', [
            'submitted' => $submitted,
            'error' => null,
        ]);
    }
}
