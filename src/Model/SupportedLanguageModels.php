<?php

declare(strict_types=1);

namespace App\Model;

use App\Repository\AssistantRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Deploy-time + user-contributed list of assistant language
 * models known to the picker.
 *
 * The catalogue's `languageModel` column is a free-typed string,
 * so this service does not gate what can be persisted — it only
 * feeds the autocomplete picker on the "Del assistent" wizard.
 * The returned list is the **union** of two sources:
 *
 * - **Deploy-time defaults** sourced from the
 *   `SUPPORTED_LANGUAGE_MODELS` env var, a comma-separated list
 *   of machine names (e.g. `gpt-4o,gpt-4o-mini,claude-3.5-sonnet`).
 *   Order matters: the env-var order defines the top of the picker
 *   list, so operators see the shortlist their install expects
 *   first.
 * - **Custom values** — every distinct `languageModel` string
 *   currently persisted on `Assistant`, so a model an operator
 *   typed in yesterday becomes a suggestion for the next operator
 *   today. Retrieved via
 *   {@see AssistantRepository::distinctLanguageModels()}.
 *
 * The two sets merge deterministically:
 *
 * - Defaults appear first, in env-var order.
 * - Custom values that are already in the defaults are dropped.
 * - Remaining custom values are appended sorted alphabetically
 *   (case-insensitive natural order).
 *
 * Blank / whitespace entries are dropped everywhere so a
 * fixture row with an empty language model or a stray trailing
 * comma in the env var doesn't inject a blank option.
 *
 * The env var is optional. When unset / empty the list still
 * carries whatever custom values the DB has; a fresh install
 * with no assistants yet yields an empty options list, which is
 * fine — the picker still accepts a free-typed value.
 */
final class SupportedLanguageModels
{
    /**
     * @param string|null          $rawSupportedLanguageModels the env-var payload; empty / null yields no deploy-time defaults
     * @param AssistantRepository  $assistantRepository        source of the "custom values in use" set
     */
    public function __construct(
        #[Autowire('%env(default::SUPPORTED_LANGUAGE_MODELS)%')]
        private readonly ?string $rawSupportedLanguageModels,
        private readonly AssistantRepository $assistantRepository,
    ) {
    }

    /**
     * Ordered, deduplicated list of language-model names to
     * offer in the picker.
     *
     * @return list<string> defaults first (env-var order), custom values appended sorted alphabetically
     */
    public function list(): array
    {
        $defaults = self::parseDefaults((string) $this->rawSupportedLanguageModels);
        $custom = $this->assistantRepository->distinctLanguageModels();

        $seen = array_fill_keys($defaults, true);
        $extras = [];
        foreach ($custom as $value) {
            $trimmed = trim($value);
            if ('' === $trimmed || isset($seen[$trimmed])) {
                continue;
            }
            $seen[$trimmed] = true;
            $extras[] = $trimmed;
        }

        // Case-insensitive natural sort so "GPT-4o" and "gpt-4o"
        // land next to each other rather than being separated by
        // ASCII case order.
        usort($extras, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return array_values(array_merge($defaults, $extras));
    }

    /**
     * Parse the raw env-var payload into an ordered list of
     * trimmed, non-empty machine names.
     *
     * @param string $raw the raw comma-separated env-var payload
     *
     * @return list<string> deploy-time defaults, in env-var order, empties dropped
     */
    private static function parseDefaults(string $raw): array
    {
        $entries = [];
        $seen = [];
        foreach (explode(',', $raw) as $entry) {
            $trimmed = trim($entry);
            if ('' === $trimmed || isset($seen[$trimmed])) {
                continue;
            }
            $seen[$trimmed] = true;
            $entries[] = $trimmed;
        }

        return $entries;
    }
}
