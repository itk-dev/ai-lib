<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Framework\SupportedFrameworks;
use App\Twig\FrameworkExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFilter;

final class FrameworkExtensionTest extends TestCase
{
    // Verifies the extension registers a `framework_label` filter.
    public function testGetFiltersRegistersFrameworkLabel(): void
    {
        $extension = new FrameworkExtension(new SupportedFrameworks('Open WebUI:openwebui'));

        $filters = $extension->getFilters();

        self::assertCount(1, $filters);
        self::assertInstanceOf(TwigFilter::class, $filters[0]);
        self::assertSame('framework_label', $filters[0]->getName());
    }

    // Tests that the filter resolves a known machine name to its readable label.
    public function testLabelResolvesKnownMachineName(): void
    {
        $extension = new FrameworkExtension(new SupportedFrameworks('Open WebUI:openwebui,Custom GPT:custom_gpt'));

        self::assertSame('Custom GPT', $extension->label('custom_gpt'));
    }

    // Ensures the filter falls back to the machine name for legacy / unknown values.
    public function testLabelFallsBackToMachineNameForUnknown(): void
    {
        $extension = new FrameworkExtension(new SupportedFrameworks('Open WebUI:openwebui'));

        self::assertSame('legacy_thing', $extension->label('legacy_thing'));
    }
}
