<?php

declare(strict_types=1);

namespace App\Validator;

/**
 * Shared validation pipeline for OpenWebUI assistant config blobs.
 *
 * Each `validate*` method runs one check and returns a
 * {@see ValidationResult}. {@see self::validate()} runs every check
 * in order and aggregates their errors so the upload AJAX endpoint
 * and the form submit handler share one pipeline.
 *
 * New checks (schema, allow-list, etc.) are added as more
 * `validate*` methods plus a line in {@see self::validate()}.
 */
final class OpenWebUiConfigValidator
{
    /**
     * Assert that `$json` is syntactically valid JSON.
     *
     * Uses `JSON_THROW_ON_ERROR` so the underlying decoder's own
     * message surfaces to the caller (line / character offsets are
     * useful when the upload UI displays the error).
     *
     * @param string $json raw uploaded payload
     *
     * @return ValidationResult valid when `$json` parses, otherwise
     *                          a single error carrying the decoder
     *                          message
     */
    public function validateSyntax(string $json): ValidationResult
    {
        try {
            json_decode($json, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return new ValidationResult([$e->getMessage()]);
        }

        return ValidationResult::valid();
    }

    /**
     * Temporary scaffolding: a deliberately slow validation that
     * always succeeds, kept here so the upload UI can be exercised
     * with the progress bar visible. Delete this method and its
     * call in {@see self::validate()} once a real long-running
     * validation lands.
     *
     * @param string $json raw uploaded payload (ignored)
     *
     * @return ValidationResult always valid
     */
    public function exampleDelay(string $json): ValidationResult
    {
        sleep(2);

        return ValidationResult::valid();
    }

    /**
     * Run every registered check in order and aggregate errors.
     *
     * @param string $json raw uploaded payload
     *
     * @return ValidationResult valid when every check passes, else
     *                          the concatenated errors from each
     *                          failing check
     */
    public function validate(string $json): ValidationResult
    {
        $errors = [
            ...$this->validateSyntax($json)->getErrors(),
            ...$this->exampleDelay($json)->getErrors(),
        ];

        return new ValidationResult($errors);
    }
}
