<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\DataSensitivity;
use PHPUnit\Framework\TestCase;

/**
 * Guards the {@see DataSensitivity} enum's backing values and the
 * translation-key builders.
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
        self::assertSame('ordinary_personal', DataSensitivity::OrdinaryPersonal->value);
        self::assertSame('confidential', DataSensitivity::Confidential->value);
        self::assertSame('sensitive_personal', DataSensitivity::SensitivePersonal->value);
    }

    // Verifies `::cases()` returns the three declared classifications, in map order.
    public function testCasesReturnsAllThree(): void
    {
        self::assertSame(
            [
                DataSensitivity::OrdinaryPersonal,
                DataSensitivity::Confidential,
                DataSensitivity::SensitivePersonal,
            ],
            DataSensitivity::cases(),
        );
    }

    // Ensures label() and description() build the expected translation keys under `assistant.data_sensitivity.*`.
    public function testLabelAndDescriptionBuildTranslationKeys(): void
    {
        self::assertSame(
            'assistant.data_sensitivity.ordinary_personal.label',
            DataSensitivity::OrdinaryPersonal->label(),
        );
        self::assertSame(
            'assistant.data_sensitivity.ordinary_personal.description',
            DataSensitivity::OrdinaryPersonal->description(),
        );
        self::assertSame(
            'assistant.data_sensitivity.confidential.label',
            DataSensitivity::Confidential->label(),
        );
        self::assertSame(
            'assistant.data_sensitivity.sensitive_personal.description',
            DataSensitivity::SensitivePersonal->description(),
        );
    }
}
