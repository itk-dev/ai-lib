<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Create users and rotate their passwords.
 *
 * Hides the Doctrine + password-hasher wiring from callers
 * (controllers, console commands, fixtures) so they work with plain
 * strings and get a persisted {@see User} back.
 */
final class UserManager
{
    /**
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param UserRepository $userRepository read-side lookup of users by email
     * @param UserPasswordHasherInterface $passwordHasher Symfony Security hasher
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Create a new persisted user with a hashed password.
     *
     * @param string $email user e-mail; must be unique
     * @param string $plainPassword clear-text password, hashed before persistence
     * @param list<string> $roles additional roles beyond the implicit `ROLE_USER`
     *
     * @return User the persisted user with an assigned id
     *
     * @throws \DomainException when a user with the same e-mail already exists
     * @throws \InvalidArgumentException when `$plainPassword` is empty
     */
    public function createUser(string $email, string $plainPassword, array $roles = []): User
    {
        if ('' === $plainPassword) {
            throw new \InvalidArgumentException('Password must not be empty.');
        }

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            throw new \DomainException(\sprintf('A user with the e-mail "%s" already exists.', $email));
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Replace a user's password with a freshly hashed copy.
     *
     * @param string $email e-mail of the user to update
     * @param string $newPlainPassword new clear-text password, hashed before persistence
     *
     * @return User the updated user
     *
     * @throws \DomainException when no user with that e-mail exists
     * @throws \InvalidArgumentException when `$newPlainPassword` is empty
     */
    public function changePassword(string $email, string $newPlainPassword): User
    {
        if ('' === $newPlainPassword) {
            throw new \InvalidArgumentException('Password must not be empty.');
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (null === $user) {
            throw new \DomainException(\sprintf('No user with the e-mail "%s" was found.', $email));
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPlainPassword));
        $this->entityManager->flush();

        return $user;
    }
}
