<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProfileController extends AbstractController
{
    public function __construct(private readonly UserManager $userManager)
    {
    }

    #[Route(path: '/profile', name: 'app_profile_show', methods: ['GET'])]
    public function show(): Response
    {
        return $this->render('profile/show.html.twig', [
            'user' => $this->currentUser(),
        ]);
    }

    #[Route(path: '/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $user = $this->currentUser();

        if ('POST' !== $request->getMethod()) {
            return $this->render('profile/edit.html.twig', [
                'user' => $user,
                'submitted_name' => $user->getName(),
                'error' => null,
            ]);
        }

        if (!$this->isCsrfTokenValid('profile-edit', (string) $request->request->get('_token'))) {
            return $this->render('profile/edit.html.twig', [
                'user' => $user,
                'submitted_name' => $user->getName(),
                'error' => 'profile.edit.error.invalid_token',
            ], new Response('', Response::HTTP_FORBIDDEN));
        }

        $submitted = (string) $request->request->get('name', '');

        try {
            $this->userManager->updateName($user, $submitted);
        } catch (\InvalidArgumentException) {
            return $this->render('profile/edit.html.twig', [
                'user' => $user,
                'submitted_name' => $submitted,
                'error' => 'profile.edit.error.empty_name',
            ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $this->addFlash('success', 'profile.edit.flash.success');

        return $this->redirectToRoute('app_profile_show');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
