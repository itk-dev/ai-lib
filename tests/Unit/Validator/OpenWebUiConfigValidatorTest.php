<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\OpenWebUiConfigValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the shared OpenWebUI config validator.
 *
 * The AJAX progress UI consumes `getChecks()` + `runCheck()`; the
 * form-submit path consumes `validate()`. Both flow through the
 * same underlying `validate*` methods.
 */
final class OpenWebUiConfigValidatorTest extends TestCase
{
    // Verifies getChecks() returns the declared check identifiers in order.
    public function testGetChecksReturnsTheDeclaredOrder(): void
    {
        $validator = new OpenWebUiConfigValidator();

        self::assertSame(['syntax', 'exampleDelay'], $validator->getChecks());
    }

    // Tests that runCheck('syntax') dispatches to the syntax validator.
    public function testRunCheckDispatchesToSyntaxValidator(): void
    {
        $validator = new OpenWebUiConfigValidator();

        $valid = $validator->runCheck('syntax', '{"name":"demo"}');
        $invalid = $validator->runCheck('syntax', '{not json');

        self::assertTrue($valid->isValid());
        self::assertFalse($invalid->isValid());
    }

    // Tests that runCheck('exampleDelay') dispatches to the scaffold validator.
    public function testRunCheckDispatchesToExampleDelayValidator(): void
    {
        $validator = new OpenWebUiConfigValidator();

        $result = $validator->runCheck('exampleDelay', '{}');

        self::assertTrue($result->isValid());
    }

    // Ensures runCheck() throws InvalidArgumentException for an unknown identifier.
    public function testRunCheckRejectsUnknownIdentifier(): void
    {
        $validator = new OpenWebUiConfigValidator();

        $this->expectException(\InvalidArgumentException::class);

        $validator->runCheck('no-such-check', '{}');
    }

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
