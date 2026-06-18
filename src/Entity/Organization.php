<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrganizationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table(name: 'organization')]
class Organization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $emails;

    #[ORM\Column(length: 255)]
    private string $defaultFramework;

    /**
     * @param list<string> $emails
     */
    public function __construct(
        string $name,
        array $emails,
        string $defaultFramework,
    ) {
        $this->name = $name;
        $this->emails = array_values($emails);
        $this->defaultFramework = $defaultFramework;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEmails(): array
    {
        return $this->emails;
    }

    /**
     * @param list<string> $emails
     */
    public function setEmails(array $emails): static
    {
        $this->emails = array_values($emails);

        return $this;
    }

    public function getDefaultFramework(): string
    {
        return $this->defaultFramework;
    }

    public function setDefaultFramework(string $defaultFramework): static
    {
        $this->defaultFramework = $defaultFramework;

        return $this;
    }
}
