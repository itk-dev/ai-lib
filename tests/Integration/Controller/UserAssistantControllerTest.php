<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Repository\AssistantRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the "Mine assistenter" page.
 *
 * The route lists assistants the logged-in user is the
 * `createdBy` blame for. Fixtures alternate creators
 * round-robin between Alice and Bob (see
 * {@see \App\DataFixtures\FixtureCreators}), so logging in as
 * one of them yields roughly half the seeded rows.
 */
final class UserAssistantControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    // Ensures an anonymous request to /mine/assistenter returns 401.
    public function testAnonymousAccessReturnsUnauthorized(): void
    {
        $this->client->request('GET', '/mine/assistenter');

        self::assertResponseStatusCodeSame(401);
    }

    // Verifies the page renders and lists only the assistants the logged-in user created.
    public function testListsOnlyCurrentUsersAssistants(): void
    {
        $this->loginAsFixture(UserFixtures::ALICE_EMAIL);
        $alice = $this->fixtureUser(UserFixtures::ALICE_EMAIL);

        $expected = self::getContainer()->get(AssistantRepository::class)->findCreatedBy($alice);
        self::assertNotEmpty($expected, 'AssistantFixtures must seed at least one row created by Alice');

        $crawler = $this->client->request('GET', '/mine/assistenter');

        self::assertResponseIsSuccessful();
        $links = $crawler->filter('main a[href^="/assistant/"]');
        self::assertCount(\count($expected), $links, 'the list must render exactly one card per Alice-created assistant');

        $hrefs = $links->each(static fn ($node) => (string) $node->attr('href'));
        foreach ($expected as $assistant) {
            self::assertContains('/assistant/'.$assistant->getId(), $hrefs);
        }
    }

    // Ensures the empty-state copy renders when the logged-in user has never created an assistant.
    public function testEmptyStateWhenUserHasNoAssistants(): void
    {
        // Colleague is approved but is not in the FixtureCreators
        // round-robin (which alternates Alice and Bob), so no
        // fixture assistant carries Colleague as createdBy.
        $this->loginAsFixture(UserFixtures::COLLEAGUE_EMAIL);

        $crawler = $this->client->request('GET', '/mine/assistenter');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('main a[href^="/assistant/"]'));
        self::assertSelectorTextContains('body', 'Ingen assistenter endnu');
    }

    private function fixtureUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        \assert($user instanceof User, 'UserFixtures must seed '.$email);

        return $user;
    }

    private function loginAsFixture(string $email): void
    {
        $this->client->loginUser($this->fixtureUser($email));
    }
}
