<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end profile view + edit flow for an authenticated user.
 *
 * Relies on the baseline `UserFixtures` (alice + bob with display
 * names "Alice" / "Bob", password `password`, `status = Approved`).
 */
final class ProfileControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testProfilePageRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/profile');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testEditPageRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/profile/edit');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testShowRendersTheCurrentUsersNameAndEmail(): void
    {
        $this->loginAsAlice();

        $crawler = $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('Alice', $body);
        self::assertStringContainsString('alice@example.test', $body);
    }

    public function testEditRendersFormPrefilledWithTheCurrentName(): void
    {
        $this->loginAsAlice();

        $crawler = $this->client->request('GET', '/profile/edit');

        self::assertResponseIsSuccessful();
        self::assertSame('Alice', $crawler->filter('input[name="name"]')->attr('value'));
    }

    public function testEditPersistsTheNewNameAndShowsTheFlash(): void
    {
        $this->loginAsAlice();

        $crawler = $this->client->request('GET', '/profile/edit');
        $form = $crawler->filter('form')->form();
        $form['name'] = 'Alice Andersen';
        $this->client->submit($form);

        self::assertResponseRedirects('/profile');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('Alice Andersen', $body);
        self::assertStringContainsString('gemt', $body);

        $reloaded = self::getContainer()
            ->get(UserRepository::class)
            ->findOneBy(['email' => 'alice@example.test']);
        self::assertNotNull($reloaded);
        self::assertSame('Alice Andersen', $reloaded->getName());
    }

    public function testEditRejectsEmptyNameAndKeepsTheCurrentValue(): void
    {
        $this->loginAsAlice();

        $crawler = $this->client->request('GET', '/profile/edit');
        $form = $crawler->filter('form')->form();
        // Bypass HTML5 `required` by clearing the input value programmatically.
        $form->setValues(['name' => '   ']);
        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('Navnet må ikke være tomt', $body);

        $reloaded = self::getContainer()
            ->get(UserRepository::class)
            ->findOneBy(['email' => 'alice@example.test']);
        self::assertNotNull($reloaded);
        self::assertSame('Alice', $reloaded->getName(), 'Empty submit must not have mutated the persisted name.');
    }

    public function testEditRejectsInvalidCsrfTokenAndDoesNotUpdate(): void
    {
        $this->loginAsAlice();

        $this->client->request('POST', '/profile/edit', [
            'name' => 'Hacker',
            '_token' => 'nope',
        ]);

        self::assertResponseStatusCodeSame(403);

        $reloaded = self::getContainer()
            ->get(UserRepository::class)
            ->findOneBy(['email' => 'alice@example.test']);
        self::assertNotNull($reloaded);
        self::assertSame('Alice', $reloaded->getName(), 'CSRF rejection must not have mutated the persisted name.');
    }

    private function loginAsAlice(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form();
        $form['_username'] = 'alice@example.test';
        $form['_password'] = 'password';
        $this->client->submit($form);
        $this->client->followRedirect();
    }
}
