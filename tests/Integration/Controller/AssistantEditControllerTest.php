<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\Assistant;
use App\Entity\User;
use App\Repository\AssistantRepository;
use App\Repository\UserRepository;
use App\Security\Roles;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the three-step assistant-edit wizard.
 *
 * The wizard shares its flow type + step partials with the create
 * wizard; the edit controller seeds a per-assistant session slot
 * from the persisted entity and routes the `metadata → receipt`
 * transition through {@see \App\Assistant\AssistantEditor} so the
 * row is updated in place rather than duplicated.
 */
final class AssistantEditControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    // Denies anonymous access via the firewall — the edit route requires a signed-in user; the branded 401 entry point handles the anonymous case.
    public function testAnonymousRequestReturnsUnauthorized(): void
    {
        $assistant = $this->assistant();

        $this->client->request('GET', '/assistant/'.$assistant->getId().'/edit');

        self::assertResponseStatusCodeSame(401);
    }

    // Denies a signed-in user who is neither the author nor an admin.
    public function testForbidsUnrelatedSignedInUser(): void
    {
        $bob = $this->userByEmail(UserFixtures::BOB_EMAIL);
        $assistant = $this->assistantOwnedBy($this->userByEmail(UserFixtures::ALICE_EMAIL));
        $this->client->loginUser($bob);

        $this->client->request('GET', '/assistant/'.$assistant->getId().'/edit');

        self::assertResponseStatusCodeSame(403);
    }

    // Grants the assistant's original curator — step 1 renders with the persisted source config in the textarea.
    public function testAuthorSeesStepOnePrefilled(): void
    {
        $alice = $this->userByEmail(UserFixtures::ALICE_EMAIL);
        $assistant = $this->assistantOwnedBy($alice);
        $this->client->loginUser($alice);

        $crawler = $this->client->request('GET', '/assistant/'.$assistant->getId().'/edit');

        self::assertResponseIsSuccessful();
        $textarea = $crawler->filter('textarea[name$="[sourceConfig]"]')->text();
        self::assertNotSame('', trim($textarea), 'source config is pre-filled from the entity');
    }

    // Verifies the edit wizard renders correctly for an assistant with no attached organisation — the hydrated draft carries `organizationId = null` on the picker.
    public function testAuthorSeesStepOneForAssistantWithoutOrganization(): void
    {
        $alice = $this->userByEmail(UserFixtures::ALICE_EMAIL);
        $repository = self::getContainer()->get(AssistantRepository::class);
        $tagless = $repository->findOneBy(['title' => 'Uden kategorier']);
        self::assertNotNull($tagless, 'fixture baseline must include the tagless edge-case entry');
        self::assertNull($tagless->getOrganization(), 'this fixture row is used precisely because it has no organization');
        $tagless->setCreatedBy($alice);
        self::getContainer()->get('doctrine.orm.entity_manager')->flush();
        $this->client->loginUser($alice);

        $this->client->request('GET', '/assistant/'.$tagless->getId().'/edit');

        self::assertResponseIsSuccessful();
    }

    // Grants site admins on any assistant, regardless of authorship.
    public function testAdminSeesStepOneEvenWhenNotAuthor(): void
    {
        $admin = $this->promoteToAdmin($this->userByEmail(UserFixtures::BOB_EMAIL));
        $assistant = $this->assistantOwnedBy($this->userByEmail(UserFixtures::ALICE_EMAIL));
        $this->client->loginUser($admin);

        $this->client->request('GET', '/assistant/'.$assistant->getId().'/edit');

        self::assertResponseIsSuccessful();
    }

    // Verifies a GET after a completed edit clears the session slot so the same URL rerenders step 1 instead of the receipt.
    public function testGetAfterCompletionRestartsAtStepOne(): void
    {
        $alice = $this->userByEmail(UserFixtures::ALICE_EMAIL);
        $assistant = $this->assistantOwnedBy($alice);
        $this->client->loginUser($alice);

        // Walk through the wizard once so the session slot ends
        // with `createdAssistantId` set.
        $crawler = $this->client->request('GET', '/assistant/'.$assistant->getId().'/edit');
        $stepOne = $crawler->selectButton('assistant_create_flow[navigator][next]')->form();
        $sourceField = $this->findFieldName($stepOne->all(), '[sourceConfig]');
        $stepOne[$sourceField] = json_encode(['name' => 'Reset', 'base_model_id' => 'gpt-4o'], \JSON_THROW_ON_ERROR);
        $crawler = $this->client->submit($stepOne);
        $stepTwo = $crawler->selectButton('assistant_create_flow[navigator][next]')->form();
        $sensitivityField = $this->findFieldName($stepTwo->all(), '[dataSensitivity]');
        $stepTwo[$sensitivityField] = 'ordinary_personal';
        $this->client->submit($stepTwo);

        // Fresh GET after completion: session slot resets and step 1
        // renders instead of the receipt.
        $crawler = $this->client->request('GET', '/assistant/'.$assistant->getId().'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('textarea[name$="[sourceConfig]"]');
        $body = $crawler->filter('body')->text();
        self::assertStringNotContainsString('Ændringer er gemt', $body);
    }

    // Ensures a step-2 submission missing the required dataSensitivity re-renders the same step with a 422.
    public function testStepTwoRejectsMissingRequiredField(): void
    {
        $alice = $this->userByEmail(UserFixtures::ALICE_EMAIL);
        $assistant = $this->assistantOwnedBy($alice);
        $this->client->loginUser($alice);

        $crawler = $this->client->request('GET', '/assistant/'.$assistant->getId().'/edit');
        $stepOne = $crawler->selectButton('assistant_create_flow[navigator][next]')->form();
        $sourceField = $this->findFieldName($stepOne->all(), '[sourceConfig]');
        $stepOne[$sourceField] = json_encode(['name' => 'Rejected', 'base_model_id' => 'gpt-4o'], \JSON_THROW_ON_ERROR);
        $crawler = $this->client->submit($stepOne);

        // Step 2 with an unset dataSensitivity — the field's
        // NotNull constraint fires and the flow re-renders step 2.
        $stepTwo = $crawler->selectButton('assistant_create_flow[navigator][next]')->form();
        $sensitivityField = $this->findFieldName($stepTwo->all(), '[dataSensitivity]');
        $stepTwo[$sensitivityField] = '';
        $this->client->submit($stepTwo);

        self::assertResponseStatusCodeSame(422);
    }

    // Full happy path: step 1 → step 2 (pre-filled) → step 3, ending with the row updated in place.
    public function testHappyPathUpdatesRowInPlace(): void
    {
        $alice = $this->userByEmail(UserFixtures::ALICE_EMAIL);
        $assistant = $this->assistantOwnedBy($alice);
        $originalId = $assistant->getId();
        $this->client->loginUser($alice);

        $crawler = $this->client->request('GET', '/assistant/'.$originalId.'/edit');
        self::assertResponseIsSuccessful();

        // Step 1: submit with a fresh valid payload so the source
        // config is replaced along with the metadata below.
        $stepOne = $crawler->selectButton('assistant_create_flow[navigator][next]')->form();
        $sourceField = $this->findFieldName($stepOne->all(), '[sourceConfig]');
        $stepOne[$sourceField] = json_encode([
            'name' => 'Edited via wizard',
            'base_model_id' => 'gpt-4o',
            'meta' => ['description' => 'Rewritten by curator'],
        ], \JSON_THROW_ON_ERROR);
        $crawler = $this->client->submit($stepOne);

        self::assertResponseIsSuccessful();

        // Step 2: title / description are pre-filled from the
        // persisted entity — the edit path does not re-run the
        // prefiller.
        $stepTwo = $crawler->selectButton('assistant_create_flow[navigator][next]')->form();
        $titleField = $this->findFieldName($stepTwo->all(), '[title]');
        self::assertSame($assistant->getTitle(), $stepTwo[$titleField]->getValue());

        // Rewrite a couple of fields.
        $stepTwo[$titleField] = 'Rewritten title';
        $descriptionField = $this->findFieldName($stepTwo->all(), '[description]');
        $stepTwo[$descriptionField] = 'Rewritten description';
        $sensitivityField = $this->findFieldName($stepTwo->all(), '[dataSensitivity]');
        $stepTwo[$sensitivityField] = 'ordinary_personal';

        $crawler = $this->client->submit($stepTwo);
        self::assertResponseIsSuccessful();

        // Step 3 shows the "Ændringer er gemt" receipt.
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('Ændringer er gemt', $body);

        // The persisted row was mutated in place — same id.
        $reloaded = self::getContainer()->get(AssistantRepository::class)->find($originalId);
        self::assertNotNull($reloaded);
        self::assertSame('Rewritten title', $reloaded->getTitle());
        self::assertSame('Rewritten description', $reloaded->getDescription());
    }

    private function assistant(): Assistant
    {
        $assistant = self::getContainer()
            ->get(AssistantRepository::class)
            ->findOneBy(['title' => 'Borgerservice-vejviser']);
        self::assertNotNull($assistant, 'fixture baseline must include Borgerservice-vejviser');

        return $assistant;
    }

    private function assistantOwnedBy(User $owner): Assistant
    {
        $assistant = $this->assistant();
        $assistant->setCreatedBy($owner);
        self::getContainer()->get('doctrine.orm.entity_manager')->flush();

        return $assistant;
    }

    private function userByEmail(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        \assert($user instanceof User, sprintf('UserFixtures must seed %s.', $email));

        return $user;
    }

    private function promoteToAdmin(User $user): User
    {
        $user->setRoles([Roles::ADMIN]);
        self::getContainer()->get('doctrine.orm.entity_manager')->flush();

        return $user;
    }

    /**
     * Locate a field whose full name ends with the supplied suffix.
     *
     * Symfony's flow types nest field names under long
     * bracketed paths — this helper hides that from the assertions.
     *
     * @param array<string, \Symfony\Component\DomCrawler\Field\FormField> $fields
     */
    private function findFieldName(array $fields, string $suffix): string
    {
        foreach (array_keys($fields) as $name) {
            if (str_ends_with($name, $suffix)) {
                return $name;
            }
        }

        self::fail(sprintf('Form field ending with "%s" not found. Available: %s', $suffix, implode(', ', array_keys($fields))));
    }
}
