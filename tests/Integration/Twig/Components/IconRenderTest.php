<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig\Components;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Render-level coverage of the Icon component.
 *
 * The component ships a small library of decorative inline SVG
 * glyphs (pencil, upload, trash, chevron-down) that were previously
 * hand-inlined across consumer templates. Tests pin the wrapping
 * <svg> attributes and check that each `name` picks the intended
 * `<path>` so a design refresh of one glyph can't silently swap
 * another.
 */
final class IconRenderTest extends KernelTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->twig = self::getContainer()->get('twig');
    }

    // Verifies the SVG carries the shared presentation attributes (currentColor, aria-hidden, sizing class).
    public function testRendersSharedSvgAttributes(): void
    {
        $html = $this->renderInline('<twig:Icon name="pencil" />');

        self::assertMatchesRegularExpression('#<svg[^>]*stroke="currentColor"#', $html);
        self::assertMatchesRegularExpression('#<svg[^>]*aria-hidden="true"#', $html);
        self::assertMatchesRegularExpression('#<svg[^>]*class="h-5 w-5"#', $html);
        self::assertMatchesRegularExpression('#<svg[^>]*viewBox="0 0 24 24"#', $html);
    }

    // Ensures the `class` prop overrides the default sizing utilities.
    public function testClassPropOverridesDefaultSize(): void
    {
        $html = $this->renderInline('<twig:Icon name="pencil" class="h-10 w-10 text-text-muted" />');

        self::assertMatchesRegularExpression('#<svg[^>]*class="h-10 w-10 text-text-muted"#', $html);
    }

    // Verifies the pencil glyph renders the edit path.
    public function testPencilRendersEditPath(): void
    {
        $html = $this->renderInline('<twig:Icon name="pencil" />');

        self::assertStringContainsString('d="M16.862 4.487', $html);
    }

    // Verifies the upload glyph renders the upload arrow path.
    public function testUploadRendersUploadArrowPath(): void
    {
        $html = $this->renderInline('<twig:Icon name="upload" />');

        self::assertStringContainsString('d="M3 16.5v2.25', $html);
    }

    // Verifies the trash glyph renders the trash-can path.
    public function testTrashRendersTrashCanPath(): void
    {
        $html = $this->renderInline('<twig:Icon name="trash" />');

        self::assertStringContainsString('d="M14.74 9l', $html);
    }

    // Ensures chevron-down uses the smaller 12x12 viewBox and its own path.
    public function testChevronDownUsesSmallerViewBox(): void
    {
        $html = $this->renderInline('<twig:Icon name="chevron-down" class="h-3 w-3" />');

        self::assertMatchesRegularExpression('#<svg[^>]*viewBox="0 0 12 12"#', $html);
        self::assertStringContainsString('d="M3 4.5L6 7.5L9 4.5"', $html);
    }

    // Verifies an unknown name renders the shared <svg> shell without a <path>, so a typo fails loudly in review rather than silently substituting a glyph.
    public function testUnknownNameRendersEmptySvg(): void
    {
        $html = $this->renderInline('<twig:Icon name="does-not-exist" />');

        self::assertMatchesRegularExpression('#<svg[^>]*>\s*</svg>#s', $html);
    }

    // Ensures call-site attributes (e.g. data-*) spread onto the <svg> element.
    public function testCallSiteAttributesSpreadOntoSvg(): void
    {
        $html = $this->renderInline('<twig:Icon name="pencil" data-testid="edit-icon" />');

        self::assertStringContainsString('data-testid="edit-icon"', $html);
    }

    private function renderInline(string $source): string
    {
        return $this->twig->createTemplate($source)->render();
    }
}
