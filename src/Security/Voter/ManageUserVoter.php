<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Security\EmailDomain;
use App\Security\Roles;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorises a `User` action ("approve", "block", or the umbrella
 * "manage") on a target {@see User}, scoped by email domain.
 *
 * 1. The acting user must hold {@see Roles::DOMAIN_MANAGER}.
 * 2. If they hold {@see Roles::ADMIN}, allow across all domains
 *    (admins manage everyone).
 * 3. Otherwise allow iff the actor and subject share the lowercased
 *    email domain returned by {@see EmailDomain::of()}.
 *
 * The voter never reads the subject's `status` — identity state
 * (signed-in or not) is a separate concern handled by the
 * `UserCheckerInterface` (#63). Authorisation only answers "what may
 * this signed-in actor do to that target?".
 */
final class ManageUserVoter extends Voter
{
    public const string MANAGE = 'MANAGE_USER';
    public const string APPROVE = 'APPROVE_USER';
    public const string BLOCK = 'BLOCK_USER';

    private const array SUPPORTED = [self::MANAGE, self::APPROVE, self::BLOCK];

    /**
     * @param AccessDecisionManagerInterface $accessDecisionManager used to evaluate the actor's roles via the configured role hierarchy
     */
    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    /**
     * Vote only on `MANAGE_USER`, `APPROVE_USER`, `BLOCK_USER` with a
     * `User` subject. Any other combination defers.
     *
     * @param string $attribute the attribute being checked
     * @param mixed  $subject   the object the attribute is checked against
     *
     * @return bool true when this voter has an opinion to give
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof User && \in_array($attribute, self::SUPPORTED, true);
    }

    /**
     * Apply the decision rules to the actor / subject pair.
     *
     * @param string         $attribute the attribute being checked (already filtered to one of `SUPPORTED`)
     * @param User           $subject   the user being acted on
     * @param TokenInterface $token     the acting user's authentication token
     * @param Vote|null      $vote      Symfony 8 vote-explanation slot; unused here
     *
     * @return bool true to grant, false to deny
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();
        if (!$actor instanceof User) {
            return false;
        }

        if (!$this->accessDecisionManager->decide($token, [Roles::DOMAIN_MANAGER])) {
            return false;
        }

        if ($this->accessDecisionManager->decide($token, [Roles::ADMIN])) {
            return true;
        }

        $actorDomain = EmailDomain::of($actor);
        $subjectDomain = EmailDomain::of($subject);
        if (null === $actorDomain || null === $subjectDomain) {
            return false;
        }

        return $actorDomain === $subjectDomain;
    }
}
