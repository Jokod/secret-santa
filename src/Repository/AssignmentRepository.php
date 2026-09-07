<?php

namespace App\Repository;

use App\Entity\Assignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Assignment>
 */
class AssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Assignment::class);
    }

    public function hasActiveDraw(): bool
    {
        return $this->count([]) > 0;
    }

    /**
     * @return list<Assignment>
     */
    public function findAllWithParticipants(): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('s', 't')
            ->join('a.santa', 's')
            ->join('a.target', 't')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
