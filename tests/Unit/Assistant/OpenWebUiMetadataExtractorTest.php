<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant;

use App\Assistant\AssistantDraft;
use App\Assistant\OpenWebUiMetadataExtractor;
use PHPUnit\Framework\TestCase;

final class OpenWebUiMetadataExtractorTest extends TestCase
{
    // Verifies the extractor fills in every source-mapped field from a fully-shaped payload.
    public function testExtractsEveryMappedField(): void
    {
        $draft = new AssistantDraft();
        $draft->openwebuiConfig = json_encode([
            'name' => 'Demo',
            'base_model_id' => 'gpt-4o',
            'meta' => [
                'description' => 'Short description',
                'tags' => ['alpha', 'beta'],
            ],
        ], \JSON_THROW_ON_ERROR);

        (new OpenWebUiMetadataExtractor())->extractInto($draft);

        self::assertSame('Demo', $draft->title);
        self::assertSame('Short description', $draft->description);
        self::assertSame('gpt-4o', $draft->languageModel);
        self::assertSame(['alpha', 'beta'], $draft->tags);
    }

    // Verifies description falls back to params.system when meta.description is absent.
    public function testDescriptionFallsBackToParamsSystem(): void
    {
        $draft = new AssistantDraft();
        $draft->openwebuiConfig = json_encode([
            'params' => ['system' => 'System prompt content'],
        ], \JSON_THROW_ON_ERROR);

        (new OpenWebUiMetadataExtractor())->extractInto($draft);

        self::assertSame('System prompt content', $draft->description);
    }

    // Verifies language model falls back to `model` when `base_model_id` is absent.
    public function testLanguageModelFallsBackToModelKey(): void
    {
        $draft = new AssistantDraft();
        $draft->openwebuiConfig = json_encode(['model' => 'llama3.1:70b'], \JSON_THROW_ON_ERROR);

        (new OpenWebUiMetadataExtractor())->extractInto($draft);

        self::assertSame('llama3.1:70b', $draft->languageModel);
    }

    // Ensures tag objects (`{name: "…"}`) are unwrapped to plain strings.
    public function testTagObjectsAreUnwrappedToNames(): void
    {
        $draft = new AssistantDraft();
        $draft->openwebuiConfig = json_encode([
            'meta' => ['tags' => [['name' => 'alpha'], 'beta', ['other' => 'skipped']]],
        ], \JSON_THROW_ON_ERROR);

        (new OpenWebUiMetadataExtractor())->extractInto($draft);

        self::assertSame(['alpha', 'beta'], $draft->tags);
    }

    // Verifies existing (user-edited) draft fields are preserved on re-run so Back→edit→Next doesn't clobber user input.
    public function testDoesNotOverwriteFieldsAlreadySet(): void
    {
        $draft = new AssistantDraft();
        $draft->title = 'User-edited title';
        $draft->description = 'User-edited description';
        $draft->languageModel = 'user-edited-model';
        $draft->tags = ['user-tag'];
        $draft->openwebuiConfig = json_encode([
            'name' => 'From JSON',
            'base_model_id' => 'gpt-4o',
            'meta' => ['description' => 'From JSON', 'tags' => ['alpha']],
        ], \JSON_THROW_ON_ERROR);

        (new OpenWebUiMetadataExtractor())->extractInto($draft);

        self::assertSame('User-edited title', $draft->title);
        self::assertSame('User-edited description', $draft->description);
        self::assertSame('user-edited-model', $draft->languageModel);
        self::assertSame(['user-tag'], $draft->tags);
    }

    // Verifies malformed JSON is treated as "nothing to extract" — the draft stays untouched.
    public function testMalformedJsonLeavesDraftUntouched(): void
    {
        $draft = new AssistantDraft();
        $draft->openwebuiConfig = '{not json';

        (new OpenWebUiMetadataExtractor())->extractInto($draft);

        self::assertSame('', $draft->title);
        self::assertSame('', $draft->description);
        self::assertSame('', $draft->languageModel);
        self::assertSame([], $draft->tags);
    }

    // Verifies an empty tag list falls through — the draft's tags stay empty rather than getting set to [].
    public function testEmptyTagListLeavesDraftEmpty(): void
    {
        $draft = new AssistantDraft();
        $draft->openwebuiConfig = json_encode(['meta' => ['tags' => []]], \JSON_THROW_ON_ERROR);

        (new OpenWebUiMetadataExtractor())->extractInto($draft);

        self::assertSame([], $draft->tags);
    }

    // Verifies whitespace-only tag names are dropped, matching the "empty entry" filter.
    public function testWhitespaceTagsAreDropped(): void
    {
        $draft = new AssistantDraft();
        $draft->openwebuiConfig = json_encode([
            'meta' => ['tags' => ['   ', 'valid', ['name' => '  ']]],
        ], \JSON_THROW_ON_ERROR);

        (new OpenWebUiMetadataExtractor())->extractInto($draft);

        self::assertSame(['valid'], $draft->tags);
    }

    // Ensures duplicate tag names in the source are deduped in the output.
    public function testDuplicateTagsAreDeduped(): void
    {
        $draft = new AssistantDraft();
        $draft->openwebuiConfig = json_encode([
            'meta' => ['tags' => ['alpha', 'alpha', ['name' => 'alpha'], 'beta']],
        ], \JSON_THROW_ON_ERROR);

        (new OpenWebUiMetadataExtractor())->extractInto($draft);

        self::assertSame(['alpha', 'beta'], $draft->tags);
    }
}
