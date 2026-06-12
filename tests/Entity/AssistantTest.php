<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Assistant;
use App\Repository\AssistantRepository;
use App\Tests\Support\ResetsDatabaseSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Round-trips an Assistant through the entity manager so every getter
 * and setter sees real persistence + hydration rather than in-memory
 * object-graph wiring only.
 */
final class AssistantTest extends KernelTestCase
{
    use ResetsDatabaseSchemaTrait;

    private EntityManagerInterface $em;
    private AssistantRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        self::resetSchema($container->get(EntityManagerInterface::class));

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(AssistantRepository::class);
    }

    public function testPersistsAndHydratesBaseFields(): void
    {
        $assistant = new Assistant(
            title: 'Borgerservice-vejviser',
            description: 'Hjælper sagsbehandlere med at finde den rigtige paragraf.',
            languageModel: 'gpt-4o',
            framework: 'openwebui',
            tags: ['borgerservice', 'paragraf'],
        );

        $this->em->persist($assistant);
        $this->em->flush();
        $id = $assistant->getId();
        self::assertNotNull($id);

        $this->em->clear();
        $reloaded = $this->repository->find($id);

        self::assertInstanceOf(Assistant::class, $reloaded);
        self::assertSame('Borgerservice-vejviser', $reloaded->getTitle());
        self::assertSame('Hjælper sagsbehandlere med at finde den rigtige paragraf.', $reloaded->getDescription());
        self::assertSame('gpt-4o', $reloaded->getLanguageModel());
        self::assertSame('openwebui', $reloaded->getFramework());
        self::assertSame(['borgerservice', 'paragraf'], $reloaded->getTags());
    }

    public function testSettersUpdatePersistedValues(): void
    {
        $assistant = new Assistant('original-title', 'original-desc', 'gpt-4o', 'openwebui');
        $this->em->persist($assistant);
        $this->em->flush();
        $id = $assistant->getId();
        self::assertNotNull($id);

        $assistant
            ->setTitle('new-title')
            ->setDescription('new-desc')
            ->setLanguageModel('claude-3.5-sonnet')
            ->setFramework('custom-runtime')
            ->setTags(['x', 'y']);
        $this->em->flush();
        $this->em->clear();

        $reloaded = $this->repository->find($id);
        self::assertInstanceOf(Assistant::class, $reloaded);
        self::assertSame('new-title', $reloaded->getTitle());
        self::assertSame('new-desc', $reloaded->getDescription());
        self::assertSame('claude-3.5-sonnet', $reloaded->getLanguageModel());
        self::assertSame('custom-runtime', $reloaded->getFramework());
        self::assertSame(['x', 'y'], $reloaded->getTags());
    }

    public function testTagsDefaultToEmptyListAndAreReindexedOnSet(): void
    {
        $assistant = new Assistant('t', 'd', 'm', 'f');
        self::assertSame([], $assistant->getTags());

        // Setting with non-sequential keys must produce a clean list<string>
        // — `array_values()` in setTags() guarantees JSON serialises as an
        // array, not an object.
        $assistant->setTags([3 => 'alpha', 7 => 'beta']);
        self::assertSame(['alpha', 'beta'], $assistant->getTags());
    }
}
