<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Security\EmailConfirmation;
use App\Security\UserManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the public `/auth/confirm-email/{token}`
 * route.
 */
final class EmailConfirmationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    // Tests the success path: a valid token flips the user's status and renders the confirmation page.
    public function testValidTokenRendersConfirmationAndTransitionsStatus(): void
    {
        $user = self::getContainer()->get(UserManager::class)->createUser(
            'click@example.test',
            'Click',
            'pw',
            status: UserStatus::AwaitingEmailConfirmation,
        );
        $token = self::getContainer()->get(EmailConfirmation::class)->issueToken($user);

        $crawler = $this->client->request('GET', '/auth/confirm-email/'.$token);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('din e-mail er bekræftet', $crawler->filter('body')->text());

        $reloaded = self::getContainer()->get(UserRepository::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertSame(UserStatus::Pending, $reloaded->getStatus());
    }

    // Verifies an unknown token returns 410 Gone with the localised invalid-link copy.
    public function testUnknownTokenReturnsGoneWithInvalidPage(): void
    {
        $crawler = $this->client->request('GET', '/auth/confirm-email/never-issued-token-string');

        self::assertResponseStatusCodeSame(410);
        self::assertStringContainsString('Linket virker ikke', $crawler->filter('body')->text());
    }

    // Tests that a second click on the same link returns 410 — the token is single-use.
    public function testSecondClickReturnsGone(): void
    {
        $user = self::getContainer()->get(UserManager::class)->createUser(
            'twice@example.test',
            'Twice',
            'pw',
            status: UserStatus::AwaitingEmailConfirmation,
        );
        $token = self::getContainer()->get(EmailConfirmation::class)->issueToken($user);

        $this->client->request('GET', '/auth/confirm-email/'.$token);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/auth/confirm-email/'.$token);
        self::assertResponseStatusCodeSame(410);
    }

    // Verifies the public route is accessible anonymously — no firewall block.
    public function testRouteIsPublicAccess(): void
    {
        // No user, no auth — just hit the endpoint with a garbage token
        // and assert we get 410 (route reachable) rather than 401/302.
        $this->client->request('GET', '/auth/confirm-email/something-anonymous');

        self::assertResponseStatusCodeSame(410);
    }
}
