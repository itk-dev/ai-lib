<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrganizationRepositoryTest extends KernelTestCase
{
    // Tests that the repository is service-resolvable and round-trips a persisted Organization.
    public function testRepositoryIsResolvableAndPersistsRoundTrip(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $repository = $container->get(OrganizationRepository::class);
        self::assertInstanceOf(OrganizationRepository::class, $repository);

        $entityManager = $container->get(EntityManagerInterface::class);
        $organization = new Organization(
            'Aarhus Kommune',
            ['aarhus.dk'],
            'openwebui',
        );
        $entityManager->persist($organization);
        $entityManager->flush();

        $reloaded = $repository->find($organization->getId());

        self::assertNotNull($reloaded);
        self::assertSame('Aarhus Kommune', $reloaded->getName());
        self::assertSame(['aarhus.dk'], $reloaded->getEmailDomains());
        self::assertSame('openwebui', $reloaded->getDefaultFramework());
    }
}
