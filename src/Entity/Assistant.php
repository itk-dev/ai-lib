<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AssistantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssistantRepository::class)]
#[ORM\Table(name: 'assistant')]
class Assistant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    /**
     * Captured at creation time from the creator's organisation (see
     * ADR 005); stored on the assistant so an organisation switching
     * its default later does not silently rewrite older catalogue
     * rows.
     */
    #[ORM\Column(length: 255)]
    private string $languageModel;

    /**
     * @see self::$languageModel for the snapshot rationale
     */
    #[ORM\Column(length: 255)]
    private string $framework;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    /**
     * @param list<string> $tags
     */
    public function __construct(
        string $title,
        string $description,
        string $languageModel,
        string $framework,
        array $tags = [],
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->languageModel = $languageModel;
        $this->framework = $framework;
        $this->tags = array_values($tags);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLanguageModel(): string
    {
        return $this->languageModel;
    }

    public function setLanguageModel(string $languageModel): static
    {
        $this->languageModel = $languageModel;

        return $this;
    }

    public function getFramework(): string
    {
        return $this->framework;
    }

    public function setFramework(string $framework): static
    {
        $this->framework = $framework;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param list<string> $tags
     */
    public function setTags(array $tags): static
    {
        $this->tags = array_values($tags);

        return $this;
    }
}
