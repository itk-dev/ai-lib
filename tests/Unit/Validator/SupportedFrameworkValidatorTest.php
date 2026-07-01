<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Framework\SupportedFrameworks;
use App\Validator\SupportedFramework;
use App\Validator\SupportedFrameworkValidator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<SupportedFrameworkValidator>
 */
final class SupportedFrameworkValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): SupportedFrameworkValidator
    {
        return new SupportedFrameworkValidator(
            new SupportedFrameworks('Open WebUI:openwebui,Custom GPT:custom_gpt'),
        );
    }

    // Tests that a machine name on the deploy-time list passes without a violation.
    public function testSupportedMachineNamePasses(): void
    {
        $this->validator->validate('openwebui', new SupportedFramework());

        $this->assertNoViolation();
    }

    // Ensures a machine name outside the deploy-time list raises the localised violation.
    public function testUnsupportedMachineNameFails(): void
    {
        $this->validator->validate('not-real', new SupportedFramework());

        $this->buildViolation('admin.organization.form.default_framework_unknown')
            ->setParameter('{{ value }}', '"not-real"')
            ->assertRaised();
    }

    // Verifies null values pass — the constraint intentionally lets NotBlank pair with it.
    public function testNullValuePasses(): void
    {
        $this->validator->validate(null, new SupportedFramework());

        $this->assertNoViolation();
    }

    // Verifies empty strings pass — same "pair with NotBlank" contract as null.
    public function testEmptyStringPasses(): void
    {
        $this->validator->validate('', new SupportedFramework());

        $this->assertNoViolation();
    }

    // Ensures passing the wrong constraint class throws UnexpectedTypeException.
    public function testWrongConstraintTypeThrows(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('openwebui', new NotNull());
    }

    // Ensures a non-string, non-null value throws UnexpectedValueException.
    public function testNonStringValueThrows(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(42, new SupportedFramework());
    }
}
