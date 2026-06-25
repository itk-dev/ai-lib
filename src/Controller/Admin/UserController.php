<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Security\Roles;
use App\Security\UserApproval;
use App\Security\Voter\ManageUserVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Roles::DOMAIN_MANAGER)]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserApproval $userApproval,
    ) {
    }

    #[Route(path: '/admin/users', name: 'app_admin_users', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $actor = $this->currentUser();
        $statusFilter = $this->resolveStatusFilter((string) $request->query->get('status', ''));

        return $this->render('admin/user/list.html.twig', [
            'users' => $this->userRepository->findVisibleTo($actor, $statusFilter),
            'status_filter' => $statusFilter,
        ]);
    }

    #[Route(path: '/admin/users/pending', name: 'app_admin_users_pending', methods: ['GET'])]
    public function pending(): Response
    {
        return $this->redirectToRoute('app_admin_users', ['status' => UserStatus::Pending->value]);
    }

    #[Route(path: '/admin/users/{id}/approve', name: 'app_admin_user_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(ManageUserVoter::APPROVE, subject: 'user')]
    public function approve(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin-user-action', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->userApproval->approve($user);
        $this->addFlash('success', 'admin.users.flash.approved');

        return $this->redirectToBackUrl($request);
    }

    #[Route(path: '/admin/users/{id}/block', name: 'app_admin_user_block', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(ManageUserVoter::BLOCK, subject: 'user')]
    public function block(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin-user-action', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->userApproval->block($user);
        $this->addFlash('success', 'admin.users.flash.blocked');

        return $this->redirectToBackUrl($request);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    private function resolveStatusFilter(string $raw): ?UserStatus
    {
        if ('' === $raw) {
            return null;
        }

        return UserStatus::tryFrom($raw);
    }

    private function redirectToBackUrl(Request $request): Response
    {
        $back = (string) $request->request->get('back', '');
        if (str_starts_with($back, '/admin/users')) {
            return $this->redirect($back);
        }

        return $this->redirectToRoute('app_admin_users');
    }
}
