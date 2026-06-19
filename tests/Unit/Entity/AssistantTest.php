<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Assistant;
use PHPUnit\Framework\TestCase;

final class AssistantTest extends TestCase
{
    // Tests that the constructor assigns each field and reindexes the tags array via array_values().
    public function testConstructorPopulatesFieldsAndReindexesTags(): void
    {
        $assistant = new Assistant(
            'Title',
            'Description',
            'gpt-4o',
            'OpenAI',
            [5 => 'translation', 3 => 'summarisation'],
        );

        self::assertNull($assistant->getId());
        self::assertSame('Title', $assistant->getTitle());
        self::assertSame('Description', $assistant->getDescription());
        self::assertSame('gpt-4o', $assistant->getLanguageModel());
        self::assertSame('OpenAI', $assistant->getFramework());
        self::assertSame(['translation', 'summarisation'], $assistant->getTags());
    }

    // Verifies that omitting the tags argument leaves getTags() returning an empty list.
    public function testConstructorDefaultsTagsToEmptyList(): void
    {
        $assistant = new Assistant('t', 'd', 'lm', 'fw');

        self::assertSame([], $assistant->getTags());
    }

    // Tests that each setter mutates its field, returns `$this`, and that setTags() reindexes its argument.
    public function testSettersMutateAndReturnStatic(): void
    {
        $assistant = new Assistant('t', 'd', 'lm', 'fw');

        self::assertSame($assistant, $assistant->setTitle('new title'));
        self::assertSame('new title', $assistant->getTitle());

        self::assertSame($assistant, $assistant->setDescription('new description'));
        self::assertSame('new description', $assistant->getDescription());

        self::assertSame($assistant, $assistant->setLanguageModel('claude-opus-4-7'));
        self::assertSame('claude-opus-4-7', $assistant->getLanguageModel());

        self::assertSame($assistant, $assistant->setFramework('Anthropic'));
        self::assertSame('Anthropic', $assistant->getFramework());

        self::assertSame($assistant, $assistant->setTags([9 => 'a', 1 => 'b']));
        self::assertSame(['a', 'b'], $assistant->getTags());
    }
}
