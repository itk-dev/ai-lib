<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\AssistantFixtures;
use App\Entity\Assistant;
use App\Repository\AssistantRepository;
use App\Tests\Support\ResetsDatabaseSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AssistantFixturesTest extends KernelTestCase
{
    use ResetsDatabaseSchemaTrait;

    public function testLoadsFiveDetailedAndFifteenGenerated(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        self::resetSchema($em);

        $container->get(AssistantFixtures::class)->load($em);

        /** @var AssistantRepository $repository */
        $repository = $container->get(AssistantRepository::class);
        $all = $repository->findBy([], ['id' => 'ASC']);

        self::assertCount(20, $all);

        $detailedTitles = array_map(
            static fn (Assistant $a) => $a->getTitle(),
            \array_slice($all, 0, 5),
        );
        self::assertSame(
            [
                'Borgerservice-vejviser',
                'Mødereferent',
                'Journaliseringsassistent',
                'Skole- og dagtilbudssvar',
                'Tilsynsrapport-assistent',
            ],
            $detailedTitles,
        );

        // The 15 generated entries should each carry the kommune in
        // the title (the detailed five don't), and every row should be
        // a unique (title, description) combination.
        $generated = \array_slice($all, 5);
        self::assertCount(15, $generated);
        foreach ($generated as $assistant) {
            self::assertStringContainsString(' – ', $assistant->getTitle(), 'generated titles include the kommune');
            self::assertStringContainsString('Delt af ', $assistant->getDescription());
        }
        $signatures = array_map(
            static fn (Assistant $a) => $a->getTitle().'|'.$a->getDescription(),
            $generated,
        );
        self::assertSame($signatures, array_unique($signatures), 'every generated entry must be unique');

        // Language model rotation reaches all five values.
        $models = array_unique(array_map(
            static fn (Assistant $a) => $a->getLanguageModel(),
            $generated,
        ));
        sort($models);
        self::assertSame(
            ['claude-3.5-sonnet', 'gpt-4o', 'gpt-4o-mini', 'llama-3.1-70b', 'mistral-large'],
            $models,
        );
    }

    public function testGenerationIsDeterministic(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        /** @var AssistantRepository $repository */
        $repository = $container->get(AssistantRepository::class);

        self::resetSchema($em);
        $container->get(AssistantFixtures::class)->load($em);
        $firstRun = array_map(
            static fn (Assistant $a) => $a->getTitle(),
            $repository->findBy([], ['id' => 'ASC']),
        );

        // Re-seed from a clean schema. Clear the identity map first or
        // the second persist sees stale managed entities from run #1
        // and refuses to assign their freshly-generated IDs.
        $em->clear();
        self::resetSchema($em);
        $container->get(AssistantFixtures::class)->load($em);
        $secondRun = array_map(
            static fn (Assistant $a) => $a->getTitle(),
            $repository->findBy([], ['id' => 'ASC']),
        );

        self::assertSame($firstRun, $secondRun);
    }
}
