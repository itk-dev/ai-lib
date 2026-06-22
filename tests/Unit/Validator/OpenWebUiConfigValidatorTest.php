<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\OpenWebUiConfigValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the shared OpenWebUI config validator.
 *
 * `exampleDelay()` and `validate()` invoke `sleep(2)` per spec —
 * the scaffolding test exercises that path once, the syntax path
 * (which doesn't sleep) covers the cheap branch.
 */
final class OpenWebUiConfigValidatorTest extends TestCase
{
    // Tests that validateSyntax() returns valid for parseable JSON.
    public function testValidateSyntaxAcceptsValidJson(): void
    {
        $validator = new OpenWebUiConfigValidator();

        $result = $validator->validateSyntax('{"name":"demo"}');

        self::assertTrue($result->isValid());
        self::assertSame([], $result->getErrors());
    }

    // Ensures validateSyntax() returns the decoder error when the input does not parse.
    public function testValidateSyntaxRejectsMalformedJson(): void
    {
        $validator = new OpenWebUiConfigValidator();

        $result = $validator->validateSyntax('{not json');

        self::assertFalse($result->isValid());
        self::assertCount(1, $result->getErrors());
        self::assertNotSame('', $result->getErrors()[0]);
    }

    // Verifies exampleDelay() always returns a valid result (scaffold method).
    public function testExampleDelayAlwaysReturnsValid(): void
    {
        $validator = new OpenWebUiConfigValidator();

        $result = $validator->exampleDelay('{}');

        self::assertTrue($result->isValid());
    }

    // Tests that validate() composes a valid result when every check passes.
    public function testValidateAggregatesValidWhenAllChecksPass(): void
    {
        $validator = new OpenWebUiConfigValidator();

        $result = $validator->validate('{"ok":true}');

        self::assertTrue($result->isValid());
        self::assertSame([], $result->getErrors());
    }

    // Ensures validate() surfaces every failing check's errors (syntax check fails first).
    public function testValidateAggregatesErrorsFromFailingChecks(): void
    {
        $validator = new OpenWebUiConfigValidator();

        $result = $validator->validate('{not json');

        self::assertFalse($result->isValid());
        self::assertNotEmpty($result->getErrors());
    }
}
