<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Assistant;
use PHPUnit\Framework\TestCase;

/**
 * Covers the `openwebui_config` field added in issue #14, kept in a
 * dedicated file so the existing AssistantTest stays untouched (per
 * the project's "tests are not modified without approval" rule).
 */
final class AssistantOpenwebuiConfigTest extends TestCase
{
    // Tests that a freshly-constructed Assistant has a null openwebuiConfig.
    public function testOpenwebuiConfigDefaultsToNull(): void
    {
        $assistant = new Assistant('t', 'd', 'lm', 'fw');

        self::assertNull($assistant->getOpenwebuiConfig());
    }

    // Verifies setOpenwebuiConfig() stores the decoded array and returns $this.
    public function testSetOpenwebuiConfigStoresAndReturnsStatic(): void
    {
        $assistant = new Assistant('t', 'd', 'lm', 'fw');
        $config = ['name' => 'demo', 'params' => ['temperature' => 0.7]];

        self::assertSame($assistant, $assistant->setOpenwebuiConfig($config));
        self::assertSame($config, $assistant->getOpenwebuiConfig());
    }

    // Ensures setOpenwebuiConfig(null) clears a previously stored value.
    public function testSetOpenwebuiConfigClearsWithNull(): void
    {
        $assistant = (new Assistant('t', 'd', 'lm', 'fw'))->setOpenwebuiConfig(['x' => 1]);

        $assistant->setOpenwebuiConfig(null);

        self::assertNull($assistant->getOpenwebuiConfig());
    }
}
