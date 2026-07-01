<?php

declare(strict_types=1);

namespace App\Framework;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Deploy-time list of assistant frameworks the install supports.
 *
 * Sourced from the `SUPPORTED_FRAMEWORKS` env var as a
 * comma-separated list of `Readable Name:machine_name` pairs. The
 * machine name is what {@see \App\Entity\Organization::$defaultFramework}
 * and {@see \App\Entity\Assistant::$framework} store; the readable
 * name is display-only.
 *
 * Parsing rules — deliberately strict, since the list is
 * deploy-time config:
 *
 * - Split on `,`, trim each entry.
 * - Skip empty entries.
 * - Split each entry on the *last* `:` — the shape is
 *   `Readable Name:machine_name`, machine names are constrained
 *   to `[a-z0-9_-]+`, so any earlier `:` characters belong to
 *   the readable name (`"Kind: One:kind_one"` → readable
 *   `"Kind: One"`, machine `"kind_one"`).
 * - Fail-fast at boot on any malformed entry (no `:`, empty
 *   machine name, machine name that doesn't match the regex).
 * - When the env var is empty / unset, fall back to the single
 *   hard-coded default `Open WebUI:openwebui` so a fresh install
 *   boots without extra setup.
 *
 * The first entry doubles as the install-wide default new
 * organisations pre-select in `/admin/organizations/new`.
 */
final class SupportedFrameworks
{
    /**
     * Regex the parser applies to every machine name to catch typos
     * before they ever hit the entity's setter.
     */
    private const string MACHINE_NAME_PATTERN = '/^[a-z0-9_-]+$/';

    /**
     * Ordered map, in env-var order.
     *
     * @var array<string, string> readable name → machine name
     */
    private readonly array $entries;

    /**
     * @param string|null $rawSupportedFrameworks the env-var payload; empty / null falls back to the hard-coded default
     *
     * @throws \InvalidArgumentException when any entry is malformed (no colon, empty machine name, or machine name outside `[a-z0-9_-]+`)
     */
    public function __construct(
        #[Autowire('%env(default::SUPPORTED_FRAMEWORKS)%')]
        ?string $rawSupportedFrameworks,
    ) {
        $entries = self::parse((string) $rawSupportedFrameworks);
        if ([] === $entries) {
            $entries = ['Open WebUI' => 'openwebui'];
        }

        $this->entries = $entries;
    }

    /**
     * Ordered map of every supported framework, ready for
     * `ChoiceType`'s `choices` option (Symfony's ChoiceType
     * expects `label => value`, which is the shape this method
     * already returns).
     *
     * @return array<string, string> readable name → machine name, in env-var order
     */
    public function list(): array
    {
        return $this->entries;
    }

    /**
     * @param string $machineName the machine name to check
     *
     * @return bool whether the machine name is on the deploy-time list
     */
    public function isSupported(string $machineName): bool
    {
        return \in_array($machineName, $this->entries, true);
    }

    /**
     * Install-wide default framework — the first entry's machine
     * name — for pre-selecting a value on the "new organisation"
     * form or seeding a fresh row.
     *
     * @return string the first supported machine name
     */
    public function default(): string
    {
        /** @var string $firstKey — parse() / the hard-coded fallback guarantee at least one entry */
        $firstKey = array_key_first($this->entries);

        return $this->entries[$firstKey];
    }

    /**
     * Resolve a machine name to its readable display label.
     *
     * Falls back to the raw machine name when the input isn't on
     * the list, so legacy rows (or rows saved before an entry
     * was removed from `SUPPORTED_FRAMEWORKS`) still render
     * something meaningful in the catalogue facet.
     *
     * @param string $machineName the stored machine name
     *
     * @return string the human-readable label, or the machine name itself when unknown
     */
    public function label(string $machineName): string
    {
        $label = array_search($machineName, $this->entries, true);

        return false === $label ? $machineName : $label;
    }

    /**
     * Parse the raw env-var payload into an ordered `readable → machine` map.
     *
     * @param string $raw the raw comma-separated env-var payload
     *
     * @return array<string, string> readable → machine, in the order the env var lists them
     *
     * @throws \InvalidArgumentException when any non-empty entry is malformed
     */
    private static function parse(string $raw): array
    {
        $entries = [];
        foreach (explode(',', $raw) as $entry) {
            $trimmed = trim($entry);
            if ('' === $trimmed) {
                continue;
            }

            $colon = strrpos($trimmed, ':');
            if (false === $colon) {
                throw new \InvalidArgumentException(\sprintf(
                    'SUPPORTED_FRAMEWORKS entry "%s" is missing the "Readable:machine" colon separator.',
                    $trimmed,
                ));
            }

            $readable = trim(substr($trimmed, 0, $colon));
            $machine = trim(substr($trimmed, $colon + 1));

            if ('' === $readable) {
                throw new \InvalidArgumentException(\sprintf(
                    'SUPPORTED_FRAMEWORKS entry "%s" has an empty readable name.',
                    $trimmed,
                ));
            }
            if ('' === $machine) {
                throw new \InvalidArgumentException(\sprintf(
                    'SUPPORTED_FRAMEWORKS entry "%s" has an empty machine name.',
                    $trimmed,
                ));
            }
            if (1 !== preg_match(self::MACHINE_NAME_PATTERN, $machine)) {
                throw new \InvalidArgumentException(\sprintf(
                    'SUPPORTED_FRAMEWORKS entry "%s" has a machine name "%s" outside [a-z0-9_-].',
                    $trimmed,
                    $machine,
                ));
            }

            $entries[$readable] = $machine;
        }

        return $entries;
    }
}
