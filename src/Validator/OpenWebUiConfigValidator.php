<?php

declare(strict_types=1);

namespace App\Validator;

/**
 * Shared validation pipeline for OpenWebUI assistant config blobs.
 *
 * Each entry in {@see self::CHECKS} maps to a `validate*` method
 * below. The same list drives:
 *
 * - the AJAX upload UI (one HTTP call per check so the progress
 *   bar can step forward one notch as each check returns), and
 * - the synchronous {@see self::validate()} used by the form
 *   submit handler.
 *
 * New checks are added by adding a method and a list entry; the
 * progress bar's "denominator" follows automatically.
 */
final class OpenWebUiConfigValidator
{
    /**
     * Ordered list of check identifiers exposed to the client.
     *
     * The order is significant: it's the order the AJAX UI steps
     * through and the order {@see self::validate()} aggregates
     * errors in.
     */
    private const array CHECKS = ['syntax', 'exampleDelay'];

    /**
     * @return list<string> the check identifiers in declared order
     */
    public function getChecks(): array
    {
        return self::CHECKS;
    }

    /**
     * Run a single named check.
     *
     * @param string $name one of the identifiers from {@see self::getChecks()}
     * @param string $json raw uploaded payload
     *
     * @return ValidationResult the result of that check
     *
     * @throws \InvalidArgumentException when `$name` is not a registered check
     */
    public function runCheck(string $name, string $json): ValidationResult
    {
        return match ($name) {
            'syntax' => $this->validateSyntax($json),
            'exampleDelay' => $this->exampleDelay($json),
            default => throw new \InvalidArgumentException(\sprintf('Unknown check "%s".', $name)),
        };
    }

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
     * entry in {@see self::CHECKS} once a real long-running
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
     * Used by the form-submit path which doesn't need per-check
     * progress feedback. The AJAX upload path calls
     * {@see self::runCheck()} per check instead.
     *
     * @param string $json raw uploaded payload
     *
     * @return ValidationResult valid when every check passes, else
     *                          the concatenated errors from each
     *                          failing check
     */
    public function validate(string $json): ValidationResult
    {
        $errors = [];
        foreach (self::CHECKS as $check) {
            $errors = [...$errors, ...$this->runCheck($check, $json)->getErrors()];
        }

        return new ValidationResult($errors);
    }
}
