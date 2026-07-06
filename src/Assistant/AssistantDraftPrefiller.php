<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Assistant\Format\FormatAdapterRegistry;

/**
 * Pre-fills step 2 of the create wizard from the config pasted on
 * step 1, in whatever format it was detected as.
 *
 * Detects the source format, records it on the draft, and copies the
 * canonical model's fields into the still-empty review fields. The
 * copy is deliberately forgiving: only empty destination fields are
 * touched, so a Back → edit → Next round-trip never clobbers a value
 * the user already changed. An unrecognised payload leaves the draft
 * untouched — the step-1 constraint reports the error separately.
 */
final class AssistantDraftPrefiller
{
    /**
     * @param FormatAdapterRegistry $formats detects the format and converts it to the canonical model
     */
    public function __construct(private readonly FormatAdapterRegistry $formats)
    {
    }

    /**
     * Detect `$draft->sourceConfig`'s format and pre-fill the draft.
     *
     * Sets `$draft->framework` to the detected format id, then fills
     * empty `title` / `description` / `languageModel` / `tags` from
     * the canonical model. Description falls back to the system prompt
     * when the source carries no explicit description. A payload no
     * adapter recognises is a no-op.
     *
     * @param AssistantDraft $draft the DTO to mutate in place
     */
    public function prefill(AssistantDraft $draft): void
    {
        $adapter = $this->formats->detect($draft->sourceConfig);
        if (null === $adapter) {
            return;
        }

        // detect() matched, so the payload is valid for this adapter
        // and parseToSource() won't reject it.
        $draft->framework = $adapter->id();
        $canonical = $adapter->sourceToCanonical($adapter->parseToSource($draft->sourceConfig));

        if ('' === $draft->title && '' !== $canonical->name) {
            $draft->title = $canonical->name;
        }

        if ('' === $draft->description) {
            $description = $canonical->description ?? $canonical->systemPrompt;
            if (null !== $description && '' !== $description) {
                $draft->description = $description;
            }
        }

        if ('' === $draft->languageModel && null !== $canonical->baseModel && '' !== $canonical->baseModel) {
            $draft->languageModel = $canonical->baseModel;
        }

        if ([] === $draft->tags && [] !== $canonical->tags) {
            $draft->tags = $canonical->tags;
        }
    }
}
