<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig\Components;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Render-level coverage of the Heading component.
 *
 * Pins two additive extensions that the audit-and-swap pass
 * depends on: the small `xs` size token (`text-lg`) used by admin
 * dialog titles, and pass-through of call-site attributes (e.g.
 * `id`) so a heading can be the target of an `aria-labelledby`
 * on a wrapping `<dialog>` without wrapping the component in an
 * extra element.
 */
final class HeadingRenderTest extends KernelTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->twig = self::getContainer()->get('twig');
    }

    // Verifies size=xs resolves to a text-lg utility, filling the smallest step in the heading scale.
    public function testXsSizeRendersTextLg(): void
    {
        $html = $this->renderInline('<twig:Heading level="2" size="xs">Title</twig:Heading>');

        self::assertMatchesRegularExpression('#<h2[^>]*class="[^"]*text-lg[^"]*"#', $html);
        self::assertStringContainsString('Title', $html);
    }

    // Ensures xs still defaults to font-semibold so it reads as a heading.
    public function testXsDefaultWeightIsSemibold(): void
    {
        $html = $this->renderInline('<twig:Heading level="2" size="xs">Title</twig:Heading>');

        self::assertMatchesRegularExpression('#<h2[^>]*class="[^"]*font-semibold[^"]*"#', $html);
    }

    // Verifies call-site attributes (e.g. id) forward onto the heading element so a <dialog aria-labelledby> can point at it.
    public function testAttributesForwardOntoHeadingElement(): void
    {
        $html = $this->renderInline('<twig:Heading level="2" size="xs" id="dialog-title">Preview</twig:Heading>');

        self::assertMatchesRegularExpression('#<h2[^>]*id="dialog-title"[^>]*>#', $html);
    }

    // Ensures existing consumers (level + class only, no attributes) still render as before.
    public function testClassPropStillAppendsWithoutAttributes(): void
    {
        $html = $this->renderInline('<twig:Heading level="1" class="mb-6">Hello</twig:Heading>');

        self::assertMatchesRegularExpression('#<h1[^>]*class="[^"]*mb-6[^"]*"#', $html);
        self::assertStringContainsString('Hello', $html);
    }

    private function renderInline(string $source): string
    {
        return $this->twig->createTemplate($source)->render();
    }
}
