<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Entity\Assistant;
use App\Entity\Tag;
use App\Repository\TagRepository;
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
     * @param TagRepository            $tags          resolves tag names to shared Tag entities
     */
    public function __construct(
        private readonly OpenWebUiConfigValidator $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly TagRepository $tags,
    ) {
    }

    /**
     * Validate the uploaded JSON and persist a new Assistant.
     *
     * The raw config text — pretty-printed by the upload widget,
     * pasted by hand, or whatever shape the operator typed — is
     * decoded to a PHP array before persistence. Storage is the
     * `openwebui_config` Doctrine `JSON` column, which re-encodes
     * the array without whitespace, so whatever indentation the
     * caller passed in collapses to minified JSON on disk.
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
            tags: $this->resolveTags($tags),
        );
        $assistant->setOpenwebuiConfig($decoded);

        $this->entityManager->persist($assistant);
        $this->entityManager->flush();

        return $assistant;
    }

    /**
     * Resolve a list of tag names to shared {@see Tag} entities.
     *
     * Reuses an existing tag when one already carries the name — so the
     * unique-name constraint holds and the catalogue's tag facet stays
     * deduplicated — and creates a new (unpersisted) tag otherwise; the
     * assistant's cascade persists any new tags on flush. Duplicate names
     * within one submission collapse to a single entity.
     *
     * @param list<string> $names submitted tag names, already trimmed and non-empty
     *
     * @return list<Tag> one entity per distinct name, in first-seen order
     */
    private function resolveTags(array $names): array
    {
        $resolved = [];
        foreach ($names as $name) {
            $resolved[$name] ??= $this->tags->findOneByName($name) ?? new Tag($name);
        }

        return array_values($resolved);
    }
}
