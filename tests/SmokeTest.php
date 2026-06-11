<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SmokeTest extends KernelTestCase
{
    public function testKernelBoots(): void
    {
        $kernel = self::bootKernel();

        self::assertInstanceOf(Kernel::class, $kernel);
        self::assertSame('test', $kernel->getEnvironment());
    }
}
