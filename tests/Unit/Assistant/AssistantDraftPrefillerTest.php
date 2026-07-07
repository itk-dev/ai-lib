<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant;

use App\Assistant\AssistantDraft;
use App\Assistant\AssistantDraftPrefiller;
use App\Assistant\Format\FormatAdapterRegistry;
use App\Assistant\Format\OpenWebUiAdapter;
use App\Assistant\Model\ModelMap;
use App\Assistant\OpenWebUiConfigSanitizer;
use App\Assistant\OpenWebUiModelNormalizer;
use App\Validator\OpenWebUiConfigValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the step-2 draft pre-filler.
 */
final class AssistantDraftPrefillerTest extends TestCase
{
    private function prefiller(): AssistantDraftPrefiller
    {
        return new AssistantDraftPrefiller(
            new FormatAdapterRegistry([
                new OpenWebUiAdapter(
                    new OpenWebUiConfigValidator(\dirname(__DIR__, 3).'/config/schema/openwebui-model.json'),
                    new OpenWebUiModelNormalizer(),
                    new OpenWebUiConfigSanitizer(),
                    new ModelMap(\dirname(__DIR__, 3).'/config/model_map.yaml'),
                ),
            ]),
            new ModelMap(\dirname(__DIR__, 3).'/config/model_map.yaml'),
        );
    }

    // Verifies an empty draft is filled from the detected format and records the format id.
    public function testPrefillsEmptyDraftAndSetsFramework(): void
    {
        $draft = new AssistantDraft();
        $draft->sourceConfig = json_encode([
            'name' => 'Demo assistant',
            'base_model_id' => 'gpt-4o',
            'meta' => ['description' => 'A demo assistant', 'tags' => ['alpha', 'beta']],
        ], \JSON_THROW_ON_ERROR);

        $this->prefiller()->prefill($draft);

        self::assertSame('openwebui', $draft->framework);
        self::assertSame('Demo assistant', $draft->title);
        self::assertSame('A demo assistant', $draft->description);
        self::assertSame('gpt-4o', $draft->languageModel);
        self::assertSame(['alpha', 'beta'], $draft->tags);
    }

    // Verifies description falls back to the system prompt when the source has no meta.description.
    public function testDescriptionFallsBackToSystemPrompt(): void
    {
        $draft = new AssistantDraft();
        $draft->sourceConfig = json_encode([
            'name' => 'Demo',
            'params' => ['system' => 'You are helpful.'],
        ], \JSON_THROW_ON_ERROR);

        $this->prefiller()->prefill($draft);

        self::assertSame('You are helpful.', $draft->description);
    }

    // Verifies language model falls back to the legacy `model` key.
    public function testLanguageModelFallsBackToModelKey(): void
    {
        $draft = new AssistantDraft();
        $draft->sourceConfig = json_encode(['name' => 'Demo', 'model' => 'llama3.1:70b'], \JSON_THROW_ON_ERROR);

        $this->prefiller()->prefill($draft);

        self::assertSame('llama3.1:70b', $draft->languageModel);
    }

    // Verifies the detected model is folded onto its canonical id for the selector default.
    public function testLanguageModelIsNormalisedToCanonicalId(): void
    {
        $draft = new AssistantDraft();
        $draft->sourceConfig = json_encode(['name' => 'Demo', 'base_model_id' => 'llama3.2:latest'], \JSON_THROW_ON_ERROR);

        $this->prefiller()->prefill($draft);

        self::assertSame('llama-3.2', $draft->languageModel);
    }

    // Verifies pre-set fields survive a re-run so Back→edit→Next doesn't clobber user input.
    public function testDoesNotOverwriteExistingFields(): void
    {
        $draft = new AssistantDraft();
        $draft->title = 'User title';
        $draft->description = 'User description';
        $draft->languageModel = 'user-model';
        $draft->tags = ['user-tag'];
        $draft->sourceConfig = json_encode([
            'name' => 'From JSON',
            'base_model_id' => 'gpt-4o',
            'meta' => ['description' => 'From JSON', 'tags' => ['alpha']],
        ], \JSON_THROW_ON_ERROR);

        $this->prefiller()->prefill($draft);

        self::assertSame('User title', $draft->title);
        self::assertSame('User description', $draft->description);
        self::assertSame('user-model', $draft->languageModel);
        self::assertSame(['user-tag'], $draft->tags);
    }

    // Verifies an unrecognised payload leaves the draft untouched.
    public function testUnrecognisedConfigLeavesDraftUntouched(): void
    {
        $draft = new AssistantDraft();
        $draft->sourceConfig = '{not json';

        $this->prefiller()->prefill($draft);

        self::assertSame('', $draft->title);
        self::assertSame('', $draft->description);
        self::assertSame('', $draft->languageModel);
        self::assertSame([], $draft->tags);
    }
}
