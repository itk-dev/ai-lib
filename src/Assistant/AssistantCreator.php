<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Assistant\Format\FormatAdapterRegistry;
use App\Entity\Assistant;
use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Owns the "create an assistant from form data" flow.
 *
 * Sits between {@see \App\Controller\AssistantCreateController}
 * and Doctrine + the format adapters so the controller stays thin:
 * it parses the request, calls one method here, and renders the
 * response.
 */
final class AssistantCreator
{
    /**
     * @param FormatAdapterRegistry  $formats       resolves the adapter that validates and parses the upload
     * @param EntityManagerInterface $entityManager Doctrine entity manager that persists the Assistant
     * @param TagRepository          $tags          resolves tag names to shared Tag entities
     */
    public function __construct(
        private readonly FormatAdapterRegistry $formats,
        private readonly EntityManagerInterface $entityManager,
        private readonly TagRepository $tags,
    ) {
    }

    /**
     * Validate the uploaded config and persist a new Assistant.
     *
     * The raw config text is handed to the adapter for `$framework`,
     * which validates it, reduces it to that format's model, and
     * strips instance-specific data and PII. Only the cleaned source
     * dict is stored in the `source_config` Doctrine `JSON` column
     * — the uploading user's details, access grants, timestamps, and
     * knowledge references never reach the database.
     *
     * @param string       $title                title of the assistant
     * @param string       $description          long-form description
     * @param string       $languageModel        model identifier snapshot (e.g. `gpt-4o`)
     * @param string       $framework            format/framework id; selects the adapter (e.g. `openwebui`)
     * @param list<string> $tags                 zero or more catalogue tags
     * @param string       $rawConfig            raw uploaded config; validated then parsed by the adapter
     *
     * @return Assistant the persisted assistant with its id assigned
     *
     * @throws InvalidAssistantInputException when the config fails validation
     */
    public function create(
        string $title,
        string $description,
        string $languageModel,
        string $framework,
        array $tags,
        string $rawConfig,
    ): Assistant {
        $source = $this->formats->get($framework)->parseToSource($rawConfig);

        $assistant = new Assistant(
            title: $title,
            description: $description,
            languageModel: $languageModel,
            framework: $framework,
            tags: $this->resolveTags($tags),
        );
        $assistant->setSourceConfig($source);

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
