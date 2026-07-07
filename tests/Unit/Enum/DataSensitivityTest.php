<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\DataSensitivity;
use PHPUnit\Framework\TestCase;

/**
 * Guards the {@see DataSensitivity} enum's backing values.
 *
 * Persisted onto the `assistant.data_sensitivity` column as strings —
 * any rename here is a data-migration concern, so pin every case's
 * backing value with a test.
 */
final class DataSensitivityTest extends TestCase
{
    // Verifies each case's backing value stays stable so persisted rows keep round-tripping.
    public function testBackingValuesArePinned(): void
    {
        self::assertSame('public', DataSensitivity::Public->value);
        self::assertSame('internal', DataSensitivity::Internal->value);
        self::assertSame('personal', DataSensitivity::Personal->value);
        self::assertSame('sensitive_personal', DataSensitivity::SensitivePersonal->value);
    }

    // Verifies `::cases()` returns the four declared classifications, in map order.
    public function testCasesReturnsAllFour(): void
    {
        self::assertSame(
            [
                DataSensitivity::Public,
                DataSensitivity::Internal,
                DataSensitivity::Personal,
                DataSensitivity::SensitivePersonal,
            ],
            DataSensitivity::cases(),
        );
    }
}
