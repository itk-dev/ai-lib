<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Assistant\Format\FormatAdapterRegistry;
use App\Entity\Assistant;

/**
 * Builds a re-importable config download for an assistant.
 *
 * Reads the assistant's stored source (in its own format), lifts it
 * to the neutral {@see \App\Assistant\Format\CanonicalModel} with the
 * current catalogue edits applied, then renders it through the target
 * adapter. Target defaults to the assistant's own format (a faithful
 * round-trip); a different target produces a cross-format export,
 * which is lossy for fields the target can't represent.
 */
final class AssistantExporter
{
    /**
     * @param FormatAdapterRegistry $formats resolves the source and target format adapters
     */
    public function __construct(private readonly FormatAdapterRegistry $formats)
    {
    }

    /**
     * Export `$assistant` to `$targetFormat` (or its own format).
     *
     * @param Assistant   $assistant    the assistant to export
     * @param string|null $targetFormat target format id, or null for the assistant's own format
     *
     * @return ExportedConfig the serialised payload plus response metadata
     *
     * @throws \InvalidArgumentException when the source or target format has no adapter
     */
    public function export(Assistant $assistant, ?string $targetFormat = null): ExportedConfig
    {
        $sourceAdapter = $this->formats->get($assistant->getFramework());
        $targetAdapter = $this->formats->get($targetFormat ?? $assistant->getFramework());

        $canonical = $sourceAdapter
            ->sourceToCanonical($assistant->getSourceConfig() ?? [])
            ->withEdits(
                $assistant->getTitle(),
                $assistant->getDescription(),
                $assistant->getLanguageModel(),
                $this->tagNames($assistant),
            );

        $payload = $targetAdapter->serialize($targetAdapter->canonicalToSource($canonical));

        return new ExportedConfig($payload, $targetAdapter->mediaType(), $targetAdapter->fileExtension());
    }

    /**
     * The assistant's tag names as a plain list.
     *
     * @param Assistant $assistant the assistant to read tags from
     *
     * @return list<string> the tag names in attachment order
     */
    private function tagNames(Assistant $assistant): array
    {
        return array_values($assistant->getTags()->map(static fn ($tag): string => $tag->getName())->toArray());
    }
}
