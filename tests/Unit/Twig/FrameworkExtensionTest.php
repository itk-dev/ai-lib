<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Assistant\Format\FormatAdapterRegistry;
use App\Assistant\Format\OpenWebUiAdapter;
use App\Assistant\OpenWebUiConfigSanitizer;
use App\Assistant\OpenWebUiModelNormalizer;
use App\Twig\FrameworkExtension;
use App\Validator\OpenWebUiConfigValidator;
use PHPUnit\Framework\TestCase;
use Twig\TwigFilter;

final class FrameworkExtensionTest extends TestCase
{
    private function extension(): FrameworkExtension
    {
        $adapter = new OpenWebUiAdapter(
            new OpenWebUiConfigValidator(\dirname(__DIR__, 3).'/config/schema/openwebui-model.json'),
            new OpenWebUiModelNormalizer(),
            new OpenWebUiConfigSanitizer(),
        );

        return new FrameworkExtension(new FormatAdapterRegistry([$adapter]));
    }

    // Verifies the extension registers a `framework_label` filter.
    public function testGetFiltersRegistersFrameworkLabel(): void
    {
        $filters = $this->extension()->getFilters();

        self::assertCount(1, $filters);
        self::assertInstanceOf(TwigFilter::class, $filters[0]);
        self::assertSame('framework_label', $filters[0]->getName());
    }

    // Tests that the filter resolves a registered format id to its adapter label.
    public function testLabelResolvesRegisteredFormat(): void
    {
        self::assertSame('Open WebUI', $this->extension()->label('openwebui'));
    }

    // Ensures the filter falls back to the id for legacy / unregistered values.
    public function testLabelFallsBackToIdForUnknown(): void
    {
        self::assertSame('legacy_thing', $this->extension()->label('legacy_thing'));
    }
}
