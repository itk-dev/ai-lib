<?php

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Security\EmailDomain;
use App\Security\Roles;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Count the users currently holding {@see Roles::ADMIN}.
     *
     * Used by {@see \App\Security\UserRoles} to enforce the
     * last-admin invariant on role mutation: the site must always
     * keep at least one administrator, so demotion attempts on
     * the only remaining admin are refused. Role storage is a
     * JSON column; SQL `LIKE` on the quoted role name is the
     * cheapest way to filter without loading every row into
     * memory.
     *
     * @return int number of users whose role list contains `ROLE_ADMIN`
     */
    public function countAdmins(): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"'.Roles::ADMIN.'"%');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count the admins who can currently log in — that is, users
     * who both hold `ROLE_ADMIN` and have `UserStatus::Approved`.
     *
     * Used by {@see \App\Security\UserApproval} to refuse blocking
     * the only remaining active admin. A blocked or pending admin
     * still holds the role, but cannot authenticate, so the
     * effective administrator pool is smaller than {@see countAdmins()}.
     * The block-guard cares about loggable admins specifically —
     * leaving zero of them is the actual lockout scenario.
     *
     * @return int number of approved users whose role list contains `ROLE_ADMIN`
     */
    public function countActiveAdmins(): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->andWhere('u.status = :status')
            ->setParameter('role', '%"'.Roles::ADMIN.'"%')
            ->setParameter('status', UserStatus::Approved->value);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find users visible to the acting user, optionally filtered by status.
     *
     * Decision flow mirrors {@see \App\Security\Voter\ManageUserVoter}:
     *
     * 1. Site admins (`ROLE_ADMIN`) see every user.
     * 2. Domain managers (`ROLE_DOMAIN_MANAGER` without admin) see only
     *    users whose email domain matches their own.
     * 3. Everyone else gets an empty result — the caller must still
     *    gate the route via `IsGranted` first, this is a belt-and-
     *    braces filter for query-level scoping.
     *
     * @param User            $actor        the acting user (whose role + email determine the scope)
     * @param UserStatus|null $statusFilter optional status filter for #64's approval-queue view
     *
     * @return list<User> users sorted by id ascending
     */
    public function findVisibleTo(User $actor, ?UserStatus $statusFilter = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC');

        $roles = $actor->getRoles();

        if (\in_array(Roles::ADMIN, $roles, true)) {
            // Admin sees everyone — no domain filter.
        } elseif (\in_array(Roles::DOMAIN_MANAGER, $roles, true)) {
            $domain = EmailDomain::of($actor);
            if (null === $domain) {
                return [];
            }
            $qb->andWhere('LOWER(u.email) LIKE :domainSuffix')
                ->setParameter('domainSuffix', '%@'.$domain);
        } else {
            return [];
        }

        if (null !== $statusFilter) {
            $qb->andWhere('u.status = :status')
                ->setParameter('status', $statusFilter->value);
        }

        /** @var list<User> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
