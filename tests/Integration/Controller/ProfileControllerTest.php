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

    // Tests that an anonymous visitor is redirected to /login when hitting /profile.
    public function testProfilePageRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/profile');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    // Tests that an anonymous visitor is redirected to /login when hitting /profile/edit.
    public function testEditPageRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/profile/edit');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    // Tests that the profile page renders the signed-in user's name and email.
    public function testShowRendersTheCurrentUsersNameAndEmail(): void
    {
        $this->loginAsAlice();

        $crawler = $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('Alice', $body);
        self::assertStringContainsString('alice@example.test', $body);
    }

    // Ensures the edit form is pre-filled with the user's current name on GET.
    public function testEditRendersFormPrefilledWithTheCurrentName(): void
    {
        $this->loginAsAlice();

        $crawler = $this->client->request('GET', '/profile/edit');

        self::assertResponseIsSuccessful();
        self::assertSame('Alice', $crawler->filter('input[name="name"]')->attr('value'));
    }

    // Verifies a successful edit persists the new name and surfaces the success flash.
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

    // Ensures a whitespace-only name is rejected with 422 and the persisted name is unchanged.
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

    // Ensures an invalid CSRF token yields 403 and the persisted name is unchanged.
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
