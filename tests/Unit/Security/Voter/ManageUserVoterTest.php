<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\User;
use App\Security\Roles;
use App\Security\Voter\ManageUserVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ManageUserVoterTest extends TestCase
{
    public function testDeniesWhenActorIsNotAUser(): void
    {
        $voter = $this->voterAllowing();
        $token = $this->tokenFor(null);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $this->userWithEmail('alice@aarhus.dk'), [ManageUserVoter::MANAGE]),
        );
    }

    public function testDeniesWhenActorLacksDomainManagerRole(): void
    {
        $voter = $this->voterWithRoles([]);
        $token = $this->tokenFor($this->userWithEmail('charlie@aarhus.dk'));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $this->userWithEmail('alice@aarhus.dk'), [ManageUserVoter::MANAGE]),
        );
    }

    public function testGrantsAdminAcrossDomains(): void
    {
        $voter = $this->voterWithRoles([Roles::DOMAIN_MANAGER, Roles::ADMIN]);
        $token = $this->tokenFor($this->userWithEmail('siteadmin@aarhus.dk'));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $this->userWithEmail('subject@aalborg.dk'), [ManageUserVoter::MANAGE]),
        );
    }

    public function testGrantsDomainManagerWithinSameDomain(): void
    {
        $voter = $this->voterWithRoles([Roles::DOMAIN_MANAGER]);
        $token = $this->tokenFor($this->userWithEmail('manager@aarhus.dk'));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $this->userWithEmail('alice@aarhus.dk'), [ManageUserVoter::APPROVE]),
        );
    }

    public function testDeniesDomainManagerAcrossDifferentDomains(): void
    {
        $voter = $this->voterWithRoles([Roles::DOMAIN_MANAGER]);
        $token = $this->tokenFor($this->userWithEmail('manager@aarhus.dk'));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $this->userWithEmail('alice@aalborg.dk'), [ManageUserVoter::BLOCK]),
        );
    }

    public function testIsCaseInsensitiveOnTheDomainComparison(): void
    {
        $voter = $this->voterWithRoles([Roles::DOMAIN_MANAGER]);
        $token = $this->tokenFor($this->userWithEmail('manager@Aarhus.DK'));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $this->userWithEmail('alice@AARHUS.dk'), [ManageUserVoter::MANAGE]),
        );
    }

    public function testDeniesWhenSubjectHasNoEmail(): void
    {
        $voter = $this->voterWithRoles([Roles::DOMAIN_MANAGER]);
        $token = $this->tokenFor($this->userWithEmail('manager@aarhus.dk'));
        $subject = new User(); // email left null

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $subject, [ManageUserVoter::MANAGE]),
        );
    }

    public function testDeniesWhenActorHasNoEmail(): void
    {
        $voter = $this->voterWithRoles([Roles::DOMAIN_MANAGER]);
        $token = $this->tokenFor(new User());

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $this->userWithEmail('alice@aarhus.dk'), [ManageUserVoter::MANAGE]),
        );
    }

    public function testAbstainsOnUnsupportedAttribute(): void
    {
        $voter = $this->voterAllowing();
        $token = $this->tokenFor($this->userWithEmail('alice@aarhus.dk'));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($token, $this->userWithEmail('bob@aarhus.dk'), ['SOMETHING_ELSE']),
        );
    }

    public function testAbstainsOnUnsupportedSubject(): void
    {
        $voter = $this->voterAllowing();
        $token = $this->tokenFor($this->userWithEmail('alice@aarhus.dk'));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($token, new \stdClass(), [ManageUserVoter::MANAGE]),
        );
    }

    private function userWithEmail(string $email): User
    {
        $user = new User();
        $user->setEmail($email);

        return $user;
    }

    /**
     * @param list<string> $tokenRoles roles the access-decision manager will consider granted
     */
    private function voterWithRoles(array $tokenRoles): ManageUserVoter
    {
        $adm = $this->createMock(AccessDecisionManagerInterface::class);
        $adm->method('decide')->willReturnCallback(
            static fn (TokenInterface $token, array $attributes): bool => \in_array($attributes[0] ?? null, $tokenRoles, true),
        );

        return new ManageUserVoter($adm);
    }

    private function voterAllowing(): ManageUserVoter
    {
        // For tests that never reach the role check (unsupported attribute / subject
        // and "actor is not a User"), the access-decision manager isn't consulted.
        return $this->voterWithRoles([Roles::DOMAIN_MANAGER, Roles::ADMIN]);
    }

    private function tokenFor(?User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
