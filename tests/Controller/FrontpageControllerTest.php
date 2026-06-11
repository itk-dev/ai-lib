<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional smoke test for the placeholder frontpage.
 *
 * Ensures that anonymous visitors to `/` receive a 200 response and
 * that the rendered HTML identifies the project. The test is committed
 * ahead of the PHPUnit setup in #31 so it begins to run automatically
 * once the test runner lands.
 */
class FrontpageControllerTest extends WebTestCase
{
    /**
     * `GET /` returns 200 and shows the project identifier.
     */
    public function testFrontpageReturns200AndShowsProjectName(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'AI Bibliotek');
        self::assertSelectorTextContains('h1', 'kommunale');
    }
}
