<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\DevTemplateMarkerExtension;
use App\Twig\DevTemplateMarkerNodeVisitor;
use PHPUnit\Framework\TestCase;

final class DevTemplateMarkerExtensionTest extends TestCase
{
    public function testRegistersVisitorInDevEnvironment(): void
    {
        $visitors = (new DevTemplateMarkerExtension('dev'))->getNodeVisitors();

        self::assertCount(1, $visitors);
        self::assertInstanceOf(DevTemplateMarkerNodeVisitor::class, $visitors[0]);
    }

    public function testRegistersNoVisitorInProdEnvironment(): void
    {
        self::assertSame(
            [],
            (new DevTemplateMarkerExtension('prod'))->getNodeVisitors(),
        );
    }

    public function testRegistersNoVisitorInTestEnvironment(): void
    {
        self::assertSame(
            [],
            (new DevTemplateMarkerExtension('test'))->getNodeVisitors(),
        );
    }
}
