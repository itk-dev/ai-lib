<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant;

use App\Assistant\AssistantExporter;
use App\Assistant\Format\FormatAdapterRegistry;
use App\Assistant\Format\OpenWebUiAdapter;
use App\Assistant\OpenWebUiConfigSanitizer;
use App\Assistant\OpenWebUiModelNormalizer;
use App\Entity\Assistant;
use App\Entity\Tag;
use App\Validator\OpenWebUiConfigValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the assistant export assembler.
 */
final class AssistantExporterTest extends TestCase
{
    private function exporter(): AssistantExporter
    {
        $adapter = new OpenWebUiAdapter(
            new OpenWebUiConfigValidator(\dirname(__DIR__, 3).'/config/schema/openwebui-model.json'),
            new OpenWebUiModelNormalizer(),
            new OpenWebUiConfigSanitizer(),
        );

        return new AssistantExporter(new FormatAdapterRegistry([$adapter]));
    }

    // Verifies export() renders the stored source with the entity's edits applied, drops the instance id, plus response metadata.
    public function testExportAppliesEntityEditsToStoredSource(): void
    {
        $assistant = new Assistant('Title', 'Desc', 'gpt-4o-mini', 'openwebui', [new Tag('a'), new Tag('b')]);
        $assistant->setSourceConfig([
            'id' => 'demo',
            'name' => 'Original',
            'base_model_id' => 'gpt-4o',
            'params' => ['system' => 'System prompt'],
            'meta' => ['description' => 'Original', 'capabilities' => ['vision' => false], 'tags' => [['name' => 'stale']]],
        ]);

        $exported = $this->exporter()->export($assistant);

        self::assertSame('application/json', $exported->mediaType);
        self::assertSame('json', $exported->extension);

        $payload = json_decode($exported->payload, associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame([[
            'name' => 'Title',
            'base_model_id' => 'gpt-4o-mini',
            'params' => ['system' => 'System prompt'],
            'meta' => [
                'description' => 'Desc',
                'capabilities' => ['vision' => false],
                'tags' => [['name' => 'a'], ['name' => 'b']],
            ],
        ]], $payload);
    }

    // Verifies a config-less assistant still exports a valid minimal model built from its columns.
    public function testExportConfiglessAssistant(): void
    {
        $assistant = new Assistant('Only columns', 'A description', 'gpt-4o', 'openwebui');

        $exported = $this->exporter()->export($assistant, 'openwebui');

        $payload = json_decode($exported->payload, associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame([[
            'name' => 'Only columns',
            'base_model_id' => 'gpt-4o',
            'meta' => ['description' => 'A description', 'tags' => []],
        ]], $payload);
    }
}
