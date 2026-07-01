<?php

declare(strict_types=1);

namespace App\Twig;

use App\Assistant\Format\FormatAdapterRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Exposes a `framework_label` Twig filter that resolves the
 * stored format id (`openwebui`) to the readable display label
 * (`Open WebUI`) of its {@see \App\Assistant\Format\FormatAdapter}.
 *
 * Falls back to the id for anything without a registered adapter,
 * so a legacy row whose format was removed still renders something
 * meaningful in the catalog facet.
 */
final class FrameworkExtension extends AbstractExtension
{
    /**
     * @param FormatAdapterRegistry $formats registry the filter delegates label lookups to
     */
    public function __construct(private readonly FormatAdapterRegistry $formats)
    {
    }

    /**
     * @return list<TwigFilter> the filter set this extension registers
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('framework_label', $this->label(...)),
        ];
    }

    /**
     * Resolve a stored format id to its readable display label.
     *
     * @param string $id the stored format identifier
     *
     * @return string the human-readable label, or the id itself when no adapter is registered
     */
    public function label(string $id): string
    {
        return $this->formats->label($id);
    }
}
