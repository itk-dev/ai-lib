<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\AllowedEmailDomains;
use PHPUnit\Framework\TestCase;

final class AllowedEmailDomainsTest extends TestCase
{
    public function testEmptyEnvProducesEmptyList(): void
    {
        $allow = new AllowedEmailDomains('');

        self::assertSame([], $allow->all());
        self::assertFalse($allow->contains('aarhus.dk'));
    }

    public function testSingleEntryIsMatched(): void
    {
        $allow = new AllowedEmailDomains('aarhus.dk');

        self::assertSame(['aarhus.dk'], $allow->all());
        self::assertTrue($allow->contains('aarhus.dk'));
    }

    public function testMultipleEntriesArePreservedInOrderAndDeduplicated(): void
    {
        $allow = new AllowedEmailDomains('aarhus.dk,kk.dk,aarhus.dk');

        self::assertSame(['aarhus.dk', 'kk.dk'], $allow->all());
    }

    public function testEntriesAreLowercasedAndTrimmed(): void
    {
        $allow = new AllowedEmailDomains('  Aarhus.DK , AARHUS.DK , kk.dk ');

        self::assertSame(['aarhus.dk', 'kk.dk'], $allow->all());
    }

    public function testBlankEntriesAreSilentlyDropped(): void
    {
        $allow = new AllowedEmailDomains(',,aarhus.dk,,');

        self::assertSame(['aarhus.dk'], $allow->all());
    }

    public function testContainsIsCaseInsensitiveAndWhitespaceTolerant(): void
    {
        $allow = new AllowedEmailDomains('aarhus.dk');

        self::assertTrue($allow->contains('AARHUS.DK'));
        self::assertTrue($allow->contains('  aarhus.dk  '));
        self::assertFalse($allow->contains('other.dk'));
    }
}
