<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Allow-list of email domains accepted by anonymous self-signup.
 *
 * Sourced from a comma-separated env var
 * (`REGISTRATION_ALLOWED_EMAIL_DOMAINS=aarhus.dk,kk.dk,…`).
 * Domains are normalised to lowercase + trimmed on construction
 * so `aarhus.dk`, `Aarhus.DK`, and `  AARHUS.dk` all match the same
 * way.
 *
 * Empty / blank entries are dropped silently — an env var like
 * `,,aarhus.dk,` is interpreted as a single-entry list.
 */
final class AllowedEmailDomains
{
    /**
     * @var list<string> lowercased + trimmed domain entries
     */
    private readonly array $domains;

    /**
     * @param string $allowedEmailDomainsRaw the comma-separated env-var payload
     */
    public function __construct(
        #[Autowire(env: 'REGISTRATION_ALLOWED_EMAIL_DOMAINS')]
        string $allowedEmailDomainsRaw,
    ) {
        $entries = [];
        foreach (explode(',', $allowedEmailDomainsRaw) as $entry) {
            $normalised = strtolower(trim($entry));
            if ('' !== $normalised) {
                $entries[] = $normalised;
            }
        }

        $this->domains = array_values(array_unique($entries));
    }

    /**
     * @return bool whether `$domain` (case-insensitive, with surrounding whitespace tolerated) is on the allow-list
     */
    public function contains(string $domain): bool
    {
        return \in_array(strtolower(trim($domain)), $this->domains, true);
    }

    /**
     * @return list<string> the normalised allow-list, for diagnostics / templating
     */
    public function all(): array
    {
        return $this->domains;
    }
}
