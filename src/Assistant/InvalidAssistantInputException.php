<?php

declare(strict_types=1);

namespace App\Assistant;

/**
 * Thrown by {@see AssistantCreator::create()} when the submitted
 * form data fails the OpenWebUI config validation pipeline.
 *
 * Carries the localised error messages from the validator so the
 * controller can render them on the form.
 */
final class InvalidAssistantInputException extends \DomainException
{
    /**
     * @param list<string> $errors the validator's per-check error messages
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Assistant input failed validation.');
    }

    /**
     * @return list<string> the validator's per-check error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
