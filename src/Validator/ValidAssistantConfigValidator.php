<?php

declare(strict_types=1);

namespace App\Validator;

use App\Assistant\Format\FormatAdapterRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Delegates {@see ValidAssistantConfig}'s check to the
 * {@see FormatAdapterRegistry} so the form gate and the
 * {@see \App\Assistant\AssistantCreator} persistence gate accept
 * exactly the same set of formats.
 */
final class ValidAssistantConfigValidator extends ConstraintValidator
{
    /**
     * @param FormatAdapterRegistry $formats the registry whose adapters validate the payload
     */
    public function __construct(private readonly FormatAdapterRegistry $formats)
    {
    }

    /**
     * Emit a violation when no registered format accepts `$value`.
     *
     * Blank values pass (paired `NotBlank` owns that). When a format's
     * {@see \App\Assistant\Format\FormatAdapter::supports()} accepts
     * the payload it is valid; otherwise the aggregated, deduped
     * validation errors from every adapter are surfaced so the user
     * sees why each candidate format rejected it.
     *
     * @param mixed      $value      the property value under validation
     * @param Constraint $constraint the constraint instance driving the check
     *
     * @throws UnexpectedTypeException  when the constraint is not a {@see ValidAssistantConfig}
     * @throws UnexpectedValueException when the annotated value isn't a string or null
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidAssistantConfig) {
            throw new UnexpectedTypeException($constraint, ValidAssistantConfig::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (null !== $this->formats->detect($value)) {
            return;
        }

        $errors = [];
        foreach (array_keys($this->formats->all()) as $id) {
            $errors = [...$errors, ...$this->formats->get($id)->validate($value)->getErrors()];
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ errors }}', implode('; ', array_values(array_unique($errors))))
            ->addViolation();
    }
}
