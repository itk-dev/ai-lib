<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Entity\Assistant;
use App\Validator\OpenWebUiConfigValidator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Owns the "create an assistant from form data" flow.
 *
 * Sits between {@see \App\Controller\AssistantCreateController}
 * and Doctrine + the validator so the controller stays thin: it
 * parses the request, calls one method here, and renders the
 * response.
 */
final class AssistantCreator
{
    /**
     * @param OpenWebUiConfigValidator $validator     full validation pipeline for the uploaded JSON
     * @param EntityManagerInterface   $entityManager Doctrine entity manager that persists the Assistant
     */
    public function __construct(
        private readonly OpenWebUiConfigValidator $validator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Validate the uploaded JSON and persist a new Assistant.
     *
     * @param string       $title                title of the assistant
     * @param string       $description          long-form description
     * @param string       $languageModel        model identifier snapshot (e.g. `gpt-4o`)
     * @param string       $framework            framework identifier snapshot (e.g. `openwebui`)
     * @param list<string> $tags                 zero or more catalogue tags
     * @param string       $openwebuiConfigJson  raw OpenWebUI export JSON; validated then decoded
     *
     * @return Assistant the persisted assistant with its id assigned
     *
     * @throws InvalidAssistantInputException when the JSON fails validation
     */
    public function create(
        string $title,
        string $description,
        string $languageModel,
        string $framework,
        array $tags,
        string $openwebuiConfigJson,
    ): Assistant {
        $result = $this->validator->validate($openwebuiConfigJson);
        if (!$result->isValid()) {
            throw new InvalidAssistantInputException($result->getErrors());
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($openwebuiConfigJson, associative: true, flags: \JSON_THROW_ON_ERROR);

        $assistant = new Assistant(
            title: $title,
            description: $description,
            languageModel: $languageModel,
            framework: $framework,
            tags: $tags,
        );
        $assistant->setOpenwebuiConfig($decoded);

        $this->entityManager->persist($assistant);
        $this->entityManager->flush();

        return $assistant;
    }
}
