<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Assistant;
use App\Repository\AssistantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AssistantRepositoryTest extends KernelTestCase
{
    public function testRepositoryIsResolvableAndPersistsRoundTrip(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $repository = $container->get(AssistantRepository::class);
        self::assertInstanceOf(AssistantRepository::class, $repository);

        $entityManager = $container->get(EntityManagerInterface::class);
        $assistant = new Assistant('Title', 'Description', 'gpt-4o', 'OpenAI', ['translation']);
        $entityManager->persist($assistant);
        $entityManager->flush();

        $reloaded = $repository->find($assistant->getId());

        self::assertNotNull($reloaded);
        self::assertSame('Title', $reloaded->getTitle());
        self::assertSame(['translation'], $reloaded->getTags());
    }
}
