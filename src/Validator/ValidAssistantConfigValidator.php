<?php

declare(strict_types=1);

namespace App\Validator;

use App\Assistant\Format\FormatAdapterRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Delegates {@see ValidAssistantConfig}'s check to the
 * {@see FormatAdapterRegistry} so the form gate and the
 * {@see \App\Assistant\AssistantCreator} persistence gate accept
 * exactly the same set of formats.
 */
final class ValidAssistantConfigValidator extends ConstraintValidator
{
    /**
     * Translation domain the per-error strings are looked up in.
     *
     * Kept separate from the `validators` domain so the finite set of
     * JSON-decoder messages can be localised without cluttering the
     * general validator catalogue. Errors not present in the catalogue
     * pass through verbatim.
     */
    public const string ERRORS_TRANSLATION_DOMAIN = 'assistant_validation';

    /**
     * @param FormatAdapterRegistry $formats    the registry whose adapters validate the payload
     * @param TranslatorInterface   $translator localises each per-error string against the {@see self::ERRORS_TRANSLATION_DOMAIN} catalogue
     */
    public function __construct(
        private readonly FormatAdapterRegistry $formats,
        private readonly TranslatorInterface $translator,
    ) {
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

        // First violation is the localised intro ("Filen er ikke en
        // gyldig assistent-konfiguration."), then one violation per
        // deduped detail line so the default `form_errors` template
        // renders the whole thing as a `<ul>` with each reason on its
        // own row instead of a `; `-joined single line.
        $this->context->buildViolation($constraint->message)->addViolation();

        foreach (array_values(array_unique($errors)) as $error) {
            $this->context->buildViolation(
                $this->translator->trans($error, [], self::ERRORS_TRANSLATION_DOMAIN),
            )->addViolation();
        }
    }
}
