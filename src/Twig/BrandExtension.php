<?php

declare(strict_types=1);

namespace App\Twig;

use App\Settings\SettingsManager;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes the brand identity globals (`brand_name`,
 * `brand_tagline`, `brand_initials`) to every Twig template.
 *
 * Delegates to {@see SettingsManager} so an admin-typed value in
 * the `Setting` table wins over the `BRAND_*` env vars, which
 * remain the deploy-time defaults. The globals were previously
 * wired directly from env in `config/packages/twig.yaml`;
 * routing them through this extension lets the admin settings
 * surface override them at runtime.
 */
final class BrandExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param SettingsManager $settings typed accessor for runtime-editable settings
     */
    public function __construct(private readonly SettingsManager $settings)
    {
    }

    /**
     * Resolve the brand globals at render time.
     *
     * Symfony calls `getGlobals()` for each environment instance
     * Twig builds; the values are resolved on each call so updates
     * persisted through the admin form take effect on the next
     * request without a cache clear.
     *
     * @return array{brand_name: string, brand_tagline: string, brand_initials: string}
     */
    public function getGlobals(): array
    {
        return [
            'brand_name' => $this->settings->getBrandName(),
            'brand_tagline' => $this->settings->getBrandTagline(),
            'brand_initials' => $this->settings->getBrandInitials(),
        ];
    }
}
