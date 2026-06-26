<?php

declare(strict_types=1);

namespace App\Tests\Integration\Assistant;

use App\Assistant\AssistantCreator;
use App\Assistant\InvalidAssistantInputException;
use App\Repository\AssistantRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration coverage of the create-an-assistant service.
 *
 * Uses the real `OpenWebUiConfigValidator` + Doctrine entity
 * manager so the persistence round-trip and the validation
 * pipeline are exercised together.
 */
final class AssistantCreatorTest extends KernelTestCase
{
    private AssistantCreator $creator;
    private AssistantRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->creator = $container->get(AssistantCreator::class);
        $this->repository = $container->get(AssistantRepository::class);
    }

    // Tests the happy path: a valid JSON payload persists an Assistant with the parsed config and the form data.
    public function testCreatePersistsAssistantWithDecodedConfig(): void
    {
        $assistant = $this->creator->create(
            'Service Test Assistant',
            'A description',
            'gpt-4o',
            'openwebui',
            ['alpha', 'beta'],
            '{"name":"demo","temperature":0.5}',
        );

        self::assertNotNull($assistant->getId());
        self::assertSame('Service Test Assistant', $assistant->getTitle());
        self::assertSame(['alpha', 'beta'], $assistant->getTags());
        self::assertSame(['name' => 'demo', 'temperature' => 0.5], $assistant->getOpenwebuiConfig());

        $reloaded = $this->repository->find($assistant->getId());
        self::assertNotNull($reloaded);
        self::assertSame(['name' => 'demo', 'temperature' => 0.5], $reloaded->getOpenwebuiConfig());
    }

    // Verifies pretty-printed JSON with whitespace and newlines is normalised to a minified array on persist.
    public function testCreateNormalisesPrettyPrintedConfigToMinifiedStorage(): void
    {
        $prettyJson = <<<'JSON'
            {
                "name": "demo",
                "temperature": 0.5,
                "tags": [
                    "alpha",
                    "beta"
                ]
            }
            JSON;

        $assistant = $this->creator->create(
            'Pretty assistant',
            'd',
            'gpt-4o',
            'openwebui',
            [],
            $prettyJson,
        );

        // The column is Doctrine `JSON` — value goes through json_decode →
        // array → json_encode (minified) on the way to the DB. Reload from
        // the repository to confirm the round-trip is value-stable.
        $reloaded = $this->repository->find($assistant->getId());
        self::assertNotNull($reloaded);
        self::assertSame(
            ['name' => 'demo', 'temperature' => 0.5, 'tags' => ['alpha', 'beta']],
            $reloaded->getOpenwebuiConfig(),
        );
    }

    // Ensures malformed JSON triggers the InvalidAssistantInputException carrying the validator errors.
    public function testCreateRejectsMalformedJsonWithErrors(): void
    {
        try {
            $this->creator->create(
                'Rejected',
                'd',
                'm',
                'f',
                [],
                '{not json',
            );
            self::fail('Expected InvalidAssistantInputException.');
        } catch (InvalidAssistantInputException $e) {
            self::assertNotEmpty($e->getErrors());
        }

        self::assertNull($this->repository->findOneBy(['title' => 'Rejected']));
    }
}
