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
use Symfony\Contracts\Translation\TranslatorInterface;

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
        // Passthrough translator: `trans($id, …)` returns the id
        // untouched, letting the tests assert against the raw
        // adapter error strings without wiring a real catalogue.
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ValidAssistantConfigValidator($this->registry(), $translator);
    }

    // Tests that a payload a registered format accepts passes without a violation.
    public function testRecognisedConfigPasses(): void
    {
        $this->validator->validate('{"name":"demo"}', new ValidAssistantConfig());

        $this->assertNoViolation();
    }

    // Ensures a schema-invalid payload raises the localised intro plus one violation per deduped detail line so the form template renders them as a list.
    public function testSchemaInvalidRaisesViolation(): void
    {
        $json = '{"base_model_id":"gpt-4o"}';
        $errors = array_values(array_unique($this->registry()->get('openwebui')->validate($json)->getErrors()));

        $this->validator->validate($json, new ValidAssistantConfig());

        $assertion = $this->buildViolation('assistant.new.step_json.invalid_config');
        foreach ($errors as $error) {
            $assertion = $assertion->buildNextViolation($error);
        }
        $assertion->assertRaised();
    }

    // Ensures malformed input raises the intro plus one per JsonException detail line, translated through the assistant_validation domain.
    public function testMalformedInputRaisesViolation(): void
    {
        $errors = array_values(array_unique($this->registry()->get('openwebui')->validate('{not json')->getErrors()));

        $this->validator->validate('{not json', new ValidAssistantConfig());

        $assertion = $this->buildViolation('assistant.new.step_json.invalid_config');
        foreach ($errors as $error) {
            $assertion = $assertion->buildNextViolation($error);
        }
        $assertion->assertRaised();
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
