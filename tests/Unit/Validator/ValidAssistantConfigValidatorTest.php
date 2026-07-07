<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Assistant\Format\FormatAdapterRegistry;
use App\Assistant\Format\OpenWebUiAdapter;
use App\Assistant\Model\ModelMap;
use App\Assistant\OpenWebUiConfigSanitizer;
use App\Assistant\OpenWebUiModelNormalizer;
use App\Validator\OpenWebUiConfigValidator;
use App\Validator\ValidAssistantConfig;
use App\Validator\ValidAssistantConfigValidator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ValidAssistantConfigValidator>
 */
final class ValidAssistantConfigValidatorTest extends ConstraintValidatorTestCase
{
    private function registry(): FormatAdapterRegistry
    {
        return new FormatAdapterRegistry([
            new OpenWebUiAdapter(
                new OpenWebUiConfigValidator(\dirname(__DIR__, 3).'/config/schema/openwebui-model.json'),
                new OpenWebUiModelNormalizer(),
                new OpenWebUiConfigSanitizer(),
                new ModelMap(\dirname(__DIR__, 3).'/config/model_map.yaml'),
            ),
        ]);
    }

    protected function createValidator(): ValidAssistantConfigValidator
    {
        return new ValidAssistantConfigValidator($this->registry());
    }

    // Tests that a payload a registered format accepts passes without a violation.
    public function testRecognisedConfigPasses(): void
    {
        $this->validator->validate('{"name":"demo"}', new ValidAssistantConfig());

        $this->assertNoViolation();
    }

    // Ensures a schema-invalid payload raises the violation carrying the aggregated errors.
    public function testSchemaInvalidRaisesViolation(): void
    {
        $json = '{"base_model_id":"gpt-4o"}';
        $expected = implode('; ', array_values(array_unique($this->registry()->get('openwebui')->validate($json)->getErrors())));

        $this->validator->validate($json, new ValidAssistantConfig());

        $this->buildViolation('assistant.new.step_json.invalid_config')
            ->setParameter('{{ errors }}', $expected)
            ->assertRaised();
    }

    // Ensures malformed input raises a violation (no format recognises it).
    public function testMalformedInputRaisesViolation(): void
    {
        $this->validator->validate('{not json', new ValidAssistantConfig());

        $this->buildViolation('assistant.new.step_json.invalid_config')->setParameters([
            '{{ errors }}' => implode('; ', array_values(array_unique(
                $this->registry()->get('openwebui')->validate('{not json')->getErrors(),
            ))),
        ])->assertRaised();
    }

    // Verifies null values pass — the constraint intentionally lets NotBlank pair with it.
    public function testNullValuePasses(): void
    {
        $this->validator->validate(null, new ValidAssistantConfig());

        $this->assertNoViolation();
    }

    // Verifies empty strings pass — same "pair with NotBlank" contract as null.
    public function testEmptyStringPasses(): void
    {
        $this->validator->validate('', new ValidAssistantConfig());

        $this->assertNoViolation();
    }

    // Ensures passing the wrong constraint class throws UnexpectedTypeException.
    public function testWrongConstraintTypeThrows(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('{"name":"demo"}', new NotNull());
    }

    // Ensures a non-string, non-null value throws UnexpectedValueException.
    public function testNonStringValueThrows(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(42, new ValidAssistantConfig());
    }
}
