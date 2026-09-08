<?php

namespace App\Repository;

use App\Entity\Participant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participant>
 */
class ParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participant::class);
    }

    public function findOneByToken(string $token): ?Participant
    {
        return $this->findOneBy(['tokenSecret' => $token]);
    }

    /**
     * @return list<Participant>
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.wishes', 'w')
            ->addSelect('w')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Participant>
     */
    public function findWithoutWishes(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.wishes', 'w')
            ->andWhere('w.id IS NULL')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
