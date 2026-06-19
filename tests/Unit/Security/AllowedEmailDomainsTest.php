<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\AllowedEmailDomains;
use PHPUnit\Framework\TestCase;

final class AllowedEmailDomainsTest extends TestCase
{
    // Tests that an empty env string yields an empty allow-list with no matches.
    public function testEmptyEnvProducesEmptyList(): void
    {
        $allow = new AllowedEmailDomains('');

        self::assertSame([], $allow->all());
        self::assertFalse($allow->contains('aarhus.dk'));
    }

    // Tests that a single allow-list entry matches the exact domain.
    public function testSingleEntryIsMatched(): void
    {
        $allow = new AllowedEmailDomains('aarhus.dk');

        self::assertSame(['aarhus.dk'], $allow->all());
        self::assertTrue($allow->contains('aarhus.dk'));
    }

    // Verifies multiple entries are kept in their original order and deduplicated.
    public function testMultipleEntriesArePreservedInOrderAndDeduplicated(): void
    {
        $allow = new AllowedEmailDomains('aarhus.dk,kk.dk,aarhus.dk');

        self::assertSame(['aarhus.dk', 'kk.dk'], $allow->all());
    }

    // Verifies entries are lowercased and trimmed during parsing.
    public function testEntriesAreLowercasedAndTrimmed(): void
    {
        $allow = new AllowedEmailDomains('  Aarhus.DK , AARHUS.DK , kk.dk ');

        self::assertSame(['aarhus.dk', 'kk.dk'], $allow->all());
    }

    // Ensures blank entries (e.g. leading/trailing commas) are silently dropped.
    public function testBlankEntriesAreSilentlyDropped(): void
    {
        $allow = new AllowedEmailDomains(',,aarhus.dk,,');

        self::assertSame(['aarhus.dk'], $allow->all());
    }

    // Verifies contains() is case-insensitive and tolerates surrounding whitespace.
    public function testContainsIsCaseInsensitiveAndWhitespaceTolerant(): void
    {
        $allow = new AllowedEmailDomains('aarhus.dk');

        self::assertTrue($allow->contains('AARHUS.DK'));
        self::assertTrue($allow->contains('  aarhus.dk  '));
        self::assertFalse($allow->contains('other.dk'));
    }
}
