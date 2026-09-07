<?php

namespace App\Repository;

use App\Entity\Exclusion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exclusion>
 */
class ExclusionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exclusion::class);
    }

    /**
     * @return list<Exclusion>
     */
    public function findAllWithParticipants(): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('s', 't')
            ->join('e.source', 's')
            ->join('e.target', 't')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, list<int>> map sourceId => list of forbidden target ids
     */
    public function getForbiddenTargetsMap(): array
    {
        $map = [];
        foreach ($this->findAll() as $exclusion) {
            $sourceId = $exclusion->getSource()->getId();
            $targetId = $exclusion->getTarget()->getId();
            if ($sourceId === null || $targetId === null) {
                continue;
            }
            $map[$sourceId][] = $targetId;
        }

        return $map;
    }
}
