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

    #[Route(path: '/admin/settings', name: 'app_admin_settings', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $submitted = [
            'admin_recipient' => $this->settingsManager->getAdminRecipient() ?? '',
        ];
        $error = null;
        $status = Response::HTTP_OK;

        if ('POST' === $request->getMethod()) {
            $submitted['admin_recipient'] = trim((string) $request->request->get('admin_recipient', ''));

            if (!$this->isCsrfTokenValid('admin-settings', (string) $request->request->get('_token'))) {
                return $this->render('admin/settings/edit.html.twig', [
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

                return $this->redirectToRoute('app_admin_settings');
            }
        }

        return $this->render('admin/settings/edit.html.twig', [
            'submitted' => $submitted,
            'error' => $error,
        ], new Response('', $status));
    }
}
