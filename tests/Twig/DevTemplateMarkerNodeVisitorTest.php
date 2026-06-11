<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\DevTemplateMarkerNodeVisitor;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\EmptyNode;

final class DevTemplateMarkerNodeVisitorTest extends TestCase
{
    public function testWrapsTopLevelBodyWithMarkers(): void
    {
        $output = $this->render(['hello.html.twig' => '<p>hi</p>']);

        self::assertSame(
            '<!-- hello.html.twig --><p>hi</p><!-- /hello.html.twig -->',
            $output,
        );
    }

    public function testWrapsBodyBlockOfExtendingTemplate(): void
    {
        $output = $this->render([
            'base.html.twig' => '[{% block body %}{% endblock %}]',
            'child.html.twig' => '{% extends "base.html.twig" %}{% block body %}hi{% endblock %}',
        ], 'child.html.twig');

        // base.html.twig is itself a non-extending template and also gets
        // its body wrapped. child.html.twig's markers live inside the
        // `body` block, between base's markers.
        self::assertSame(
            '<!-- base.html.twig -->[<!-- child.html.twig -->hi<!-- /child.html.twig -->]<!-- /base.html.twig -->',
            $output,
        );
    }

    public function testExtendingTemplateWithoutBodyBlockIsLeftAlone(): void
    {
        $output = $this->render([
            'base.html.twig' => '[{% block other %}fallback{% endblock %}]',
            'child.html.twig' => '{% extends "base.html.twig" %}{% block other %}hi{% endblock %}',
        ], 'child.html.twig');

        // No `body` block in the chain, so child.html.twig contributes no
        // markers. Base still gets its own.
        self::assertSame('<!-- base.html.twig -->[hi]<!-- /base.html.twig -->', $output);
    }

    public function testNamespacedTemplateIsSkipped(): void
    {
        $output = $this->render(['@vendor/widget.html.twig' => '<p>vendor</p>']);

        self::assertSame('<p>vendor</p>', $output);
    }

    public function testEnterNodeIsAPassThrough(): void
    {
        $visitor = new DevTemplateMarkerNodeVisitor();
        $env = new Environment(new ArrayLoader([]));
        $node = new EmptyNode();

        self::assertSame($node, $visitor->enterNode($node, $env));
    }

    public function testLeaveNodeIgnoresNonModuleNodes(): void
    {
        $visitor = new DevTemplateMarkerNodeVisitor();
        $env = new Environment(new ArrayLoader([]));
        $node = new EmptyNode();

        self::assertSame($node, $visitor->leaveNode($node, $env));
    }

    public function testPriorityIsZero(): void
    {
        self::assertSame(0, (new DevTemplateMarkerNodeVisitor())->getPriority());
    }

    /**
     * @param array<string, string> $templates
     */
    private function render(array $templates, ?string $name = null): string
    {
        $env = new Environment(new ArrayLoader($templates), ['cache' => false]);
        $env->addNodeVisitor(new DevTemplateMarkerNodeVisitor());

        return $env->render($name ?? array_key_first($templates));
    }
}
