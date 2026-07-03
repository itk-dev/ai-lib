<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AssistantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ITKDev\EntityBundle\Audit\Attribute\Auditable;

#[ORM\Entity(repositoryClass: AssistantRepository::class)]
#[ORM\Table(name: 'assistant')]
#[Auditable]
class Assistant extends AbstractEntity
{
    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 255)]
    private string $languageModel;

    /**
     * @see self::$languageModel for the snapshot rationale
     */
    #[ORM\Column(length: 255)]
    private string $framework;

    /**
     * Tags applied to this assistant, shared across the catalogue.
     *
     * Owning side of the relation: persisting an assistant cascades to
     * any new tags it carries, but de-duplication (one row per name) is
     * the caller's job — resolve existing tags through
     * {@see \App\Repository\TagRepository::findOneByName()} before
     * attaching them.
     *
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'assistant_tag')]
    private Collection $tags;

    /**
     * The uploaded assistant config for this assistant, reduced to its
     * format's model and stripped of instance-specific data and PII
     * before storage (see {@see \App\Assistant\AssistantCreator}). The
     * format that produced it is recorded in {@see self::$framework}.
     * Null for catalogue entries that pre-date the upload flow.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'source_config', type: Types::JSON, nullable: true)]
    private ?array $sourceConfig = null;

    /**
     * @param iterable<Tag> $tags tags to attach on creation
     */
    public function __construct(
        string $title,
        string $description,
        string $languageModel,
        string $framework,
        iterable $tags = [],
    ) {
        parent::__construct();
        $this->title = $title;
        $this->description = $description;
        $this->languageModel = $languageModel;
        $this->framework = $framework;
        $this->tags = new ArrayCollection();
        foreach ($tags as $tag) {
            $this->addTag($tag);
        }
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
     * @return Collection<int, Tag> the tags attached to this assistant
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * Attach a tag, ignoring duplicates.
     *
     * Idempotent: attaching a tag already present is a no-op, so callers
     * can add freely without checking membership first.
     *
     * @param Tag $tag the tag to attach
     *
     * @return $this for chaining
     */
    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    /**
     * Detach a tag.
     *
     * No-op when the tag is not attached.
     *
     * @param Tag $tag the tag to detach
     *
     * @return $this for chaining
     */
    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSourceConfig(): ?array
    {
        return $this->sourceConfig;
    }

    /**
     * @param array<string, mixed>|null $sourceConfig
     */
    public function setSourceConfig(?array $sourceConfig): static
    {
        $this->sourceConfig = $sourceConfig;

        return $this;
    }
}
