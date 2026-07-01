<?php

declare(strict_types=1);

namespace App\Tests\Unit\Framework;

use App\Framework\SupportedFrameworks;
use PHPUnit\Framework\TestCase;

/**
 * Unit-level cover of the deploy-time framework list parser.
 */
final class SupportedFrameworksTest extends TestCase
{
    // Verifies an empty env string yields an empty list with an empty default — the project ships the env var populated, so this only fires when it's cleared or unset.
    public function testEmptyEnvYieldsEmptyList(): void
    {
        $frameworks = new SupportedFrameworks('');

        self::assertSame([], $frameworks->list());
        self::assertSame('', $frameworks->default());
    }

    // Same as above but for the null case Symfony's env-var default:: syntax can produce when the var isn't defined.
    public function testNullEnvYieldsEmptyList(): void
    {
        $frameworks = new SupportedFrameworks(null);

        self::assertSame([], $frameworks->list());
        self::assertSame('', $frameworks->default());
    }

    // Ensures isSupported() returns false for anything when the list is empty.
    public function testIsSupportedIsFalseWhenListIsEmpty(): void
    {
        $frameworks = new SupportedFrameworks('');

        self::assertFalse($frameworks->isSupported('openwebui'));
    }

    // Verifies a single-entry env produces a single-key list preserving the readable name.
    public function testSingleEntryProducesSingleKeyList(): void
    {
        $frameworks = new SupportedFrameworks('Open WebUI:openwebui');

        self::assertSame(['Open WebUI' => 'openwebui'], $frameworks->list());
    }

    // Verifies multiple entries preserve their env-var order.
    public function testMultipleEntriesPreserveOrder(): void
    {
        $frameworks = new SupportedFrameworks('Open WebUI:openwebui,Custom GPT:custom_gpt,Legacy:legacy_framework');

        self::assertSame(
            ['Open WebUI' => 'openwebui', 'Custom GPT' => 'custom_gpt', 'Legacy' => 'legacy_framework'],
            $frameworks->list(),
        );
    }

    // Verifies whitespace around commas + around colons is trimmed off both halves.
    public function testWhitespaceIsTrimmedAroundEntriesAndColons(): void
    {
        $frameworks = new SupportedFrameworks('  Open WebUI  :  openwebui  ,  Custom :  custom  ');

        self::assertSame(
            ['Open WebUI' => 'openwebui', 'Custom' => 'custom'],
            $frameworks->list(),
        );
    }

    // Ensures blank / whitespace-only entries between commas are skipped without failing.
    public function testBlankEntriesAreSkipped(): void
    {
        $frameworks = new SupportedFrameworks(',,Open WebUI:openwebui,   ,Custom:custom,');

        self::assertSame(
            ['Open WebUI' => 'openwebui', 'Custom' => 'custom'],
            $frameworks->list(),
        );
    }

    // Tests that default() returns the first entry's machine name.
    public function testDefaultReturnsFirstMachineName(): void
    {
        $frameworks = new SupportedFrameworks('Second:second,First:first');

        self::assertSame('second', $frameworks->default());
    }

    // Verifies isSupported() returns true only for entries actually on the list.
    public function testIsSupportedTellsMemberFromNonMember(): void
    {
        $frameworks = new SupportedFrameworks('Open WebUI:openwebui,Custom:custom');

        self::assertTrue($frameworks->isSupported('openwebui'));
        self::assertTrue($frameworks->isSupported('custom'));
        self::assertFalse($frameworks->isSupported('unknown'));
        // The readable name is display-only — asking about it should fail.
        self::assertFalse($frameworks->isSupported('Open WebUI'));
    }

    // Verifies label() resolves a machine name to its readable display name.
    public function testLabelReturnsReadableNameForKnownMachine(): void
    {
        $frameworks = new SupportedFrameworks('Open WebUI:openwebui,Custom GPT:custom_gpt');

        self::assertSame('Open WebUI', $frameworks->label('openwebui'));
        self::assertSame('Custom GPT', $frameworks->label('custom_gpt'));
    }

    // Ensures label() falls back to the machine name when the argument isn't on the list — supporting legacy rows.
    public function testLabelFallsBackToMachineNameWhenUnknown(): void
    {
        $frameworks = new SupportedFrameworks('Open WebUI:openwebui');

        self::assertSame('legacy_framework', $frameworks->label('legacy_framework'));
    }

    // Verifies a colon inside the readable name splits on the first colon only.
    public function testColonInReadableNameSplitsOnTheFirst(): void
    {
        $frameworks = new SupportedFrameworks('Kind: One:kind_one');

        self::assertSame(['Kind: One' => 'kind_one'], $frameworks->list());
    }

    // Tests that an entry missing its colon fails fast at boot.
    public function testEntryWithoutColonThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing the "Readable:machine" colon separator');

        new SupportedFrameworks('open_webui');
    }

    // Tests that an entry with an empty readable name fails fast at boot.
    public function testEntryWithEmptyReadableNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('empty readable name');

        new SupportedFrameworks(':openwebui');
    }

    // Tests that an entry with an empty machine name fails fast at boot.
    public function testEntryWithEmptyMachineNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('empty machine name');

        new SupportedFrameworks('Open WebUI:');
    }

    // Tests that a non-conforming machine name (uppercase, spaces, punctuation) fails fast at boot.
    public function testMachineNameOutsideAllowedCharactersThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('outside [a-z0-9_-]');

        new SupportedFrameworks('Open WebUI:Open WebUI');
    }
}
