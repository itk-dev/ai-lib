<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\SupportedLanguageModels;
use App\Repository\AssistantRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage of {@see SupportedLanguageModels}: parse the
 * env var, merge with the repository's custom values, apply the
 * ordering + dedup rules.
 *
 * The `AssistantRepository` collaborator is fully mocked — the
 * repo query itself is covered by the integration suite.
 */
final class SupportedLanguageModelsTest extends TestCase
{
    // Tests that a comma-separated env var yields the defaults in order.
    public function testParsesDefaultsInEnvVarOrder(): void
    {
        $service = $this->build('gpt-4o,gpt-4o-mini,claude-3.5-sonnet', []);

        self::assertSame(['gpt-4o', 'gpt-4o-mini', 'claude-3.5-sonnet'], $service->list());
    }

    // Ensures whitespace around entries is trimmed and empty entries dropped.
    public function testTrimsAndDropsEmptyDefaults(): void
    {
        $service = $this->build('  gpt-4o , ,  claude-3.5-sonnet  , ', []);

        self::assertSame(['gpt-4o', 'claude-3.5-sonnet'], $service->list());
    }

    // Ensures null / empty env var yields no defaults but still surfaces custom values.
    public function testEmptyEnvVarStillReturnsCustomValues(): void
    {
        $service = $this->build(null, ['mistral-large']);

        self::assertSame(['mistral-large'], $service->list());
    }

    // Verifies duplicates within the env var itself are deduped.
    public function testDedupsDuplicateEntriesInEnvVar(): void
    {
        $service = $this->build('gpt-4o,gpt-4o,claude-3.5-sonnet,gpt-4o', []);

        self::assertSame(['gpt-4o', 'claude-3.5-sonnet'], $service->list());
    }

    // Verifies custom values already covered by defaults are dropped from the extras list.
    public function testDropsCustomValuesAlreadyCoveredByDefaults(): void
    {
        $service = $this->build('gpt-4o,claude-3.5-sonnet', ['gpt-4o', 'llama-3.1-70b']);

        self::assertSame(['gpt-4o', 'claude-3.5-sonnet', 'llama-3.1-70b'], $service->list());
    }

    // Verifies extras (custom values not on defaults) are sorted alphabetically, case-insensitive natural order.
    public function testSortsExtrasCaseInsensitiveNaturalOrder(): void
    {
        $service = $this->build('gpt-4o', ['zephyr', 'ALPHA-alpha', 'mistral-large', 'llama-3.1-70b']);

        self::assertSame(
            ['gpt-4o', 'ALPHA-alpha', 'llama-3.1-70b', 'mistral-large', 'zephyr'],
            $service->list(),
        );
    }

    // Ensures blank / whitespace-only custom values from the repo are dropped defensively.
    public function testDropsBlankCustomValues(): void
    {
        $service = $this->build('gpt-4o', ['', '   ', 'mistral-large']);

        self::assertSame(['gpt-4o', 'mistral-large'], $service->list());
    }

    // Ensures duplicate custom values from the repo are deduped in the merged output.
    public function testDedupsDuplicateCustomValues(): void
    {
        $service = $this->build('', ['mistral-large', 'mistral-large', 'llama-3.1-70b']);

        self::assertSame(['llama-3.1-70b', 'mistral-large'], $service->list());
    }

    // Verifies null env var + empty custom values yields an empty list — the picker's fallback shape.
    public function testEmptyEverythingYieldsEmptyList(): void
    {
        $service = $this->build(null, []);

        self::assertSame([], $service->list());
    }

    /**
     * @param list<string> $custom values the mocked AssistantRepository returns
     */
    private function build(?string $envVar, array $custom): SupportedLanguageModels
    {
        $repository = $this->createMock(AssistantRepository::class);
        $repository->method('distinctLanguageModels')->willReturn($custom);

        return new SupportedLanguageModels($envVar, $repository);
    }
}
