<?php

declare(strict_types=1);

namespace App\Twig;

use App\Framework\SupportedFrameworks;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Exposes a `framework_label` Twig filter that resolves the
 * stored machine name (`openwebui`) to the readable display
 * label (`Open WebUI`) configured in `SUPPORTED_FRAMEWORKS`.
 *
 * Falls back to the machine name for anything not on the
 * deploy-time list, so a legacy row whose framework was removed
 * from the env var still renders something meaningful in the
 * catalog facet.
 */
final class FrameworkExtension extends AbstractExtension
{
    /**
     * @param SupportedFrameworks $frameworks deploy-time list the filter delegates to
     */
    public function __construct(private readonly SupportedFrameworks $frameworks)
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
     * Resolve a stored framework machine name to its readable
     * display label.
     *
     * @param string $machineName the stored framework identifier
     *
     * @return string the human-readable name, or the machine name itself when unknown
     */
    public function label(string $machineName): string
    {
        return $this->frameworks->label($machineName);
    }
}
