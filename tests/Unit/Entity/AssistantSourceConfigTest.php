<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Assistant;
use PHPUnit\Framework\TestCase;

/**
 * Covers the `source_config` field, kept in a dedicated file so the
 * existing AssistantTest stays untouched (per the project's "tests are
 * not modified without approval" rule).
 */
final class AssistantSourceConfigTest extends TestCase
{
    // Tests that a freshly-constructed Assistant has a null sourceConfig.
    public function testSourceConfigDefaultsToNull(): void
    {
        $assistant = new Assistant('t', 'd', 'lm', 'fw');

        self::assertNull($assistant->getSourceConfig());
    }

    // Verifies setSourceConfig() stores the decoded array and returns $this.
    public function testSetSourceConfigStoresAndReturnsStatic(): void
    {
        $assistant = new Assistant('t', 'd', 'lm', 'fw');
        $config = ['name' => 'demo', 'params' => ['temperature' => 0.7]];

        self::assertSame($assistant, $assistant->setSourceConfig($config));
        self::assertSame($config, $assistant->getSourceConfig());
    }

    // Ensures setSourceConfig(null) clears a previously stored value.
    public function testSetSourceConfigClearsWithNull(): void
    {
        $assistant = (new Assistant('t', 'd', 'lm', 'fw'))->setSourceConfig(['x' => 1]);

        $assistant->setSourceConfig(null);

        self::assertNull($assistant->getSourceConfig());
    }
}
