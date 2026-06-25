<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Owns the `Pending → Approved` / `Approved → Blocked` / `Blocked →
 * Approved` transitions used by the admin approval queue (#64) and
 * the scoped user-management list (#85).
 *
 * The controller calls one of {@see self::approve()} or
 * {@see self::block()}; nothing else mutates `User::$status` after
 * the initial value is set at construction (the registration flow in
 * #62 creates `Pending` users, the console / fixture path creates
 * `Approved`). Keeping the writes in this single service means there
 * is one place to add audit logging or change notification if those
 * land later.
 */
final class UserApproval
{
    /**
     * @param EntityManagerInterface $entityManager Doctrine entity manager used to flush the status change
     */
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Mark the user as approved so they may sign in.
     *
     * Safe to call when the user is already `Approved` — the flush
     * becomes a no-op.
     *
     * @param User $user the user to approve
     */
    public function approve(User $user): void
    {
        $user->setStatus(UserStatus::Approved);
        $this->entityManager->flush();
    }

    /**
     * Mark the user as blocked so they cannot sign in (but their row
     * is preserved for audit and possible un-blocking).
     *
     * Safe to call when the user is already `Blocked` — the flush
     * becomes a no-op.
     *
     * @param User $user the user to block
     */
    public function block(User $user): void
    {
        $user->setStatus(UserStatus::Blocked);
        $this->entityManager->flush();
    }
}
