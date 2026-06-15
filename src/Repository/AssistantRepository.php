<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Assistant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Assistant>
 */
class AssistantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Assistant::class);
    }

    /**
     * Count how many distinct `languageModel` values are in use across
     * the catalogue. Powers the frontpage "Sprogmodeller" stat.
     */
    public function countDistinctLanguageModels(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.languageModel)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
