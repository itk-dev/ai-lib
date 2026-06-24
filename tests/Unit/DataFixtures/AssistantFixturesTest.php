<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures;

use App\DataFixtures\AssistantFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Assistant;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

final class AssistantFixturesTest extends TestCase
{
    // Ensures the fixture declares UserFixtures as a dependency so users load — and creators resolve — first.
    public function testDependsOnUserFixtures(): void
    {
        self::assertSame([UserFixtures::class], (new AssistantFixtures())->getDependencies());
    }

    // Tests that load() persists 21 entries — six detailed (including a tagless edge-case) and fifteen generated — with unique signatures and the expected language-model rotation.
    public function testLoadPersistsSixDetailedAndFifteenGenerated(): void
    {
        $persisted = $this->captureLoad();

        self::assertCount(21, $persisted);

        $detailedTitles = array_map(
            static fn (Assistant $a) => $a->getTitle(),
            \array_slice($persisted, 0, 6),
        );
        self::assertSame(
            [
                'Borgerservice-vejviser',
                'Mødereferent',
                'Journaliseringsassistent',
                'Skole- og dagtilbudssvar',
                'Tilsynsrapport-assistent',
                'Uden kategorier',
            ],
            $detailedTitles,
        );
        self::assertSame([], $persisted[5]->getTags(), 'tagless detailed entry must carry no tags');

        $generated = \array_slice($persisted, 6);
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

    // Ensures two consecutive load() invocations yield the same title sequence (no randomness in the fixture generator).
    public function testLoadIsDeterministic(): void
    {
        $first = array_map(
            static fn (Assistant $a) => $a->getTitle(),
            $this->captureLoad(),
        );
        $second = array_map(
            static fn (Assistant $a) => $a->getTitle(),
            $this->captureLoad(),
        );

        self::assertSame($first, $second);
    }

    /**
     * @return list<Assistant>
     */
    private function captureLoad(): array
    {
        $captured = [];
        $manager = $this->createMock(ObjectManager::class);
        $manager->method('persist')->willReturnCallback(function (object $entity) use (&$captured): void {
            \assert($entity instanceof Assistant);
            $captured[] = $entity;
        });
        $manager->expects(self::once())->method('flush');

        (new AssistantFixtures())->load($manager);

        return $captured;
    }
}
