<?php

declare(strict_types=1);

namespace App\Assistant;

/**
 * Pull suggested metadata out of a parsed OpenWebUI export so
 * step 2 of the create wizard opens with sensible pre-filled
 * values.
 *
 * Extraction rules mirror the design mocks' `suggestMetadata()`
 * function — they're deliberately forgiving: every source is
 * optional, missing values leave the corresponding draft field
 * untouched (keeps a Back-then-edit-then-Next round-trip from
 * clobbering user edits on step 2).
 */
final class OpenWebUiMetadataExtractor
{
    /**
     * Populate `$draft`'s title / description / language model /
     * tags from the OpenWebUI JSON in `$draft->openwebuiConfig`.
     *
     * The raw string is parsed once here. Malformed JSON is
     * treated as "nothing to extract" — validation of the payload
     * itself runs elsewhere (see {@see \App\Validator\OpenWebUiConfigValidator}
     * on step 1), so this method deliberately doesn't throw on a
     * bad payload; it just leaves the draft alone.
     *
     * Each destination field is only overwritten when the
     * corresponding source is present AND the destination is
     * currently empty. That means:
     *
     * - A first Next click on step 1 populates the draft.
     * - A user editing the title on step 2, going Back to fix the
     *   JSON, and clicking Next again keeps the edited title —
     *   only fields the user hasn't touched get refreshed.
     *
     * @param AssistantDraft $draft the DTO to mutate in place
     */
    public function extractInto(AssistantDraft $draft): void
    {
        $parsed = json_decode($draft->openwebuiConfig, associative: true);
        if (!\is_array($parsed)) {
            return;
        }

        if ('' === $draft->title) {
            $name = $parsed['name'] ?? null;
            if (\is_string($name) && '' !== $name) {
                $draft->title = $name;
            }
        }

        if ('' === $draft->description) {
            $description = $this->firstNonEmptyString(
                $parsed['meta']['description'] ?? null,
                $parsed['params']['system'] ?? null,
            );
            if (null !== $description) {
                $draft->description = $description;
            }
        }

        if ('' === $draft->languageModel) {
            $model = $this->firstNonEmptyString(
                $parsed['base_model_id'] ?? null,
                $parsed['model'] ?? null,
            );
            if (null !== $model) {
                $draft->languageModel = $model;
            }
        }

        if ([] === $draft->tags) {
            $tags = $this->normaliseTags($parsed['meta']['tags'] ?? null);
            if ([] !== $tags) {
                $draft->tags = $tags;
            }
        }
    }

    /**
     * Return the first argument that is a non-empty string, or
     * null when none qualify.
     */
    private function firstNonEmptyString(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (\is_string($candidate) && '' !== $candidate) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Turn a raw `meta.tags` payload — which may be a list of
     * strings, a list of `{name: "…"}` objects, or a mix — into a
     * clean list of tag names, deduped and trimmed.
     *
     * @return list<string>
     */
    private function normaliseTags(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return [];
        }

        $names = [];
        foreach ($raw as $entry) {
            $name = null;
            if (\is_string($entry)) {
                $name = $entry;
            } elseif (\is_array($entry) && isset($entry['name']) && \is_string($entry['name'])) {
                $name = $entry['name'];
            }
            if (null === $name) {
                continue;
            }
            $trimmed = trim($name);
            if ('' === $trimmed) {
                continue;
            }
            $names[$trimmed] = true;
        }

        return array_keys($names);
    }
}
