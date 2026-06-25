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
        $error = null;
        $status = Response::HTTP_OK;

        if ('POST' === $request->getMethod()) {
            $submitted = [
                'brand_name' => trim((string) $request->request->get('brand_name', '')),
                'brand_tagline' => trim((string) $request->request->get('brand_tagline', '')),
                'brand_initials' => trim((string) $request->request->get('brand_initials', '')),
            ];

            if (!$this->isCsrfTokenValid('admin-settings-site', (string) $request->request->get('_token'))) {
                return $this->render('admin/settings/site.html.twig', [
                    'submitted' => $submitted,
                    'error' => 'admin.settings.error.invalid_token',
                ], new Response('', Response::HTTP_FORBIDDEN));
            }

            $this->settingsManager->setBrandName('' === $submitted['brand_name'] ? null : $submitted['brand_name']);
            $this->settingsManager->setBrandTagline('' === $submitted['brand_tagline'] ? null : $submitted['brand_tagline']);
            $this->settingsManager->setBrandInitials('' === $submitted['brand_initials'] ? null : $submitted['brand_initials']);
            $this->addFlash('success', 'admin.settings.flash.saved');

            return $this->redirectToRoute('app_admin_settings_site');
        }

        return $this->render('admin/settings/site.html.twig', [
            'submitted' => $submitted,
            'error' => $error,
        ], new Response('', $status));
    }

    #[Route(path: '/admin/settings/email', name: 'app_admin_settings_email', methods: ['GET', 'POST'])]
    public function email(Request $request): Response
    {
        $submitted = [
            'admin_recipient' => $this->settingsManager->getAdminRecipient() ?? '',
        ];
        $error = null;
        $status = Response::HTTP_OK;

        if ('POST' === $request->getMethod()) {
            $submitted['admin_recipient'] = trim((string) $request->request->get('admin_recipient', ''));

            if (!$this->isCsrfTokenValid('admin-settings-email', (string) $request->request->get('_token'))) {
                return $this->render('admin/settings/email.html.twig', [
                    'submitted' => $submitted,
                    'error' => 'admin.settings.error.invalid_token',
                ], new Response('', Response::HTTP_FORBIDDEN));
            }

            $recipient = '' === $submitted['admin_recipient'] ? null : $submitted['admin_recipient'];

            if (null !== $recipient && !filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
                $error = 'admin.settings.error.invalid_email';
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            } else {
                $this->settingsManager->setAdminRecipient($recipient);
                $this->addFlash('success', 'admin.settings.flash.saved');

                return $this->redirectToRoute('app_admin_settings_email');
            }
        }

        return $this->render('admin/settings/email.html.twig', [
            'submitted' => $submitted,
            'error' => $error,
        ], new Response('', $status));
    }
}
