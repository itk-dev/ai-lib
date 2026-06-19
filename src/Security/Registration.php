<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\UserStatus;

/**
 * Public-signup orchestration.
 *
 * Sits between {@see \App\Controller\RegistrationController} and the
 * existing {@see UserManager}, owning the rules that distinguish a
 * legitimate self-signup attempt from one that should be rejected.
 *
 * 1. The submitted email must syntactically parse as an email.
 * 2. The right-hand side of the email must be on the allow-list
 *    {@see AllowedEmailDomains}.
 * 3. The two password fields must match.
 * 4. The name must be non-empty (rule shared with {@see UserManager}).

 *
 * On success the new {@see User} is persisted with
 * `status = Pending`. The {@see \App\Security\AccountStatusChecker}
 * keeps them out of the login flow until a domain manager approves
 * the row.
 */
final class Registration
{
    /**
     * @param UserManager          $userManager          owns the persistence + password-hashing step
     * @param AllowedEmailDomains  $allowedEmailDomains  domain allow-list parsed from the env var
     */
    public function __construct(
        private readonly UserManager $userManager,
        private readonly AllowedEmailDomains $allowedEmailDomains,
    ) {
    }

    /**
     * Run the self-signup pipeline and persist a `Pending` user.
     *
     * The thrown exceptions carry localised translation keys; the
     * controller uses them as the rendered form error.
     *
     * @param string $email                submitted email; must be valid + on the allow-list
     * @param string $name                 display name; trimmed by {@see UserManager}
     * @param string $plainPassword        chosen password
     * @param string $plainPasswordConfirm confirmation field; must match `$plainPassword`
     *
     * @return User the persisted user with `status = Pending`
     *
     * @throws RegistrationException when any of the inputs fails validation or the email already exists
     */
    public function register(
        string $email,
        string $name,
        string $plainPassword,
        string $plainPasswordConfirm,
    ): User {
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new RegistrationException('register.error.invalid_email');
        }

        // `filter_var` above guarantees the email has a non-trailing `@`,
        // so `strrpos` returns an int and the substr is the domain. See
        // the file-level comment for why this is inlined instead of
        // calling App\Security\EmailDomain::of().
        $domain = strtolower(substr($email, (int) strrpos($email, '@') + 1));
        if (!$this->allowedEmailDomains->contains($domain)) {
            throw new RegistrationException('register.error.domain_not_allowed');
        }

        if ($plainPassword !== $plainPasswordConfirm) {
            throw new RegistrationException('register.error.password_mismatch');
        }

        if ('' === trim($name)) {
            throw new RegistrationException('register.error.empty_name');
        }

        if ('' === $plainPassword) {
            throw new RegistrationException('register.error.empty_password');
        }

        try {
            return $this->userManager->createUser(
                $email,
                trim($name),
                $plainPassword,
                status: UserStatus::Pending,
            );
        } catch (\DomainException) {
            throw new RegistrationException('register.error.email_in_use');
        }
    }
}
