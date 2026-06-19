<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end self-signup flow against the real `form_login` firewall
 * and the new `AccountStatusChecker` (PR 3).
 *
 * The test env's allow-list default is `example.test` (see
 * `config/services.yaml`), so `*.@example.test` emails are accepted
 * while everything else is rejected.
 */
final class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testRegisterPageRenders(): void
    {
        $this->client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="email"]');
        self::assertSelectorExists('input[name="name"]');
        self::assertSelectorExists('input[name="password"]');
        self::assertSelectorExists('input[name="password_confirm"]');
        self::assertSelectorExists('input[name="_token"]');
    }

    public function testSuccessfulRegistrationCreatesPendingUserAndRedirects(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->filter('form')->form();
        $form['email'] = 'eve@example.test';
        $form['name'] = 'Eve';
        $form['password'] = 'secret';
        $form['password_confirm'] = 'secret';
        $this->client->submit($form);

        self::assertResponseRedirects('/register/pending');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('venter på godkendelse', $crawler->filter('body')->text());

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'eve@example.test']);
        self::assertNotNull($user);
        self::assertSame('Eve', $user->getName());
        self::assertSame(UserStatus::Pending, $user->getStatus());
    }

    public function testPendingUserCreatedByRegistrationCannotLogIn(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->filter('form')->form();
        $form['email'] = 'frank@example.test';
        $form['name'] = 'Frank';
        $form['password'] = 'secret';
        $form['password_confirm'] = 'secret';
        $this->client->submit($form);
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form();
        $form['_username'] = 'frank@example.test';
        $form['_password'] = 'secret';
        $this->client->submit($form);

        self::assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('venter på godkendelse', $crawler->filter('body')->text());
        self::assertNull(
            $this->client->getContainer()->get('security.token_storage')->getToken(),
        );
    }

    public function testRejectsNonAllowListedDomain(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->filter('form')->form();
        $form['email'] = 'mallory@other.invalid';
        $form['name'] = 'Mallory';
        $form['password'] = 'secret';
        $form['password_confirm'] = 'secret';
        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('domæne er ikke godkendt', $crawler->filter('body')->text());
        self::assertNull(
            self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'mallory@other.invalid']),
        );
    }

    public function testRejectsPasswordMismatch(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->filter('form')->form();
        $form['email'] = 'grace@example.test';
        $form['name'] = 'Grace';
        $form['password'] = 'secret';
        $form['password_confirm'] = 'different';
        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('ikke ens', $crawler->filter('body')->text());
    }

    public function testRejectsDuplicateEmail(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->filter('form')->form();
        // alice@example.test is in the baseline fixtures.
        $form['email'] = 'alice@example.test';
        $form['name'] = 'Alice';
        $form['password'] = 'secret';
        $form['password_confirm'] = 'secret';
        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('allerede en konto', $crawler->filter('body')->text());
    }

    public function testRejectsInvalidCsrfToken(): void
    {
        $this->client->request('POST', '/register', [
            'email' => 'henry@example.test',
            'name' => 'Henry',
            'password' => 'secret',
            'password_confirm' => 'secret',
            '_token' => 'nope',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertNull(
            self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'henry@example.test']),
        );
    }

    public function testLoggedInUserIsRedirectedAwayFromRegister(): void
    {
        $this->loginAsAlice();

        $this->client->request('GET', '/register');

        self::assertResponseRedirects('/');
    }

    public function testLoggedInUserIsRedirectedAwayFromPendingPage(): void
    {
        $this->loginAsAlice();

        $this->client->request('GET', '/register/pending');

        self::assertResponseRedirects('/');
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
