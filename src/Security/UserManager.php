<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Create users and change passwords without leaking persistence and
 * password-hashing wiring out into controllers or console commands.
 *
 * Callers ({@see \App\Command\UserCreateCommand},
 * {@see \App\Command\UserChangePasswordCommand}, and any future signup
 * flow) work with strings and get a persisted {@see User} back — the
 * service hides the {@see EntityManagerInterface},
 * {@see UserRepository}, and {@see UserPasswordHasherInterface}
 * collaborators.
 */
final class UserManager
{
    /**
     * @param EntityManagerInterface      $entityManager  doctrine entity manager
     *                                                    used to persist and
     *                                                    flush the {@see User}
     *                                                    aggregate
     * @param UserRepository              $userRepository read-side lookup of
     *                                                    users by email
     * @param UserPasswordHasherInterface $passwordHasher Symfony Security
     *                                                    password hasher,
     *                                                    configured via
     *                                                    `security.yaml`.
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
     * @param string       $email         the user's e-mail address; must be
     *                                    unique across the table
     * @param string       $plainPassword the password in clear-text — it is
     *                                    hashed before persistence and never
     *                                    stored as-is
     * @param list<string> $roles         additional roles to grant; the
     *                                    framework guarantees `ROLE_USER`
     *                                    implicitly so callers should leave
     *                                    this empty for plain users
     *
     * @return User the persisted user with an assigned id
     *
     * @throws \DomainException          when a user with the same e-mail
     *                                   already exists
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
     * @param string $email            the e-mail of the user whose password
     *                                 to change
     * @param string $newPlainPassword the new password in clear-text; hashed
     *                                 before persistence
     *
     * @return User the updated user
     *
     * @throws \DomainException          when no user with that e-mail exists
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
