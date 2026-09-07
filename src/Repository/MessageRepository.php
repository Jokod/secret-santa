<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\Participant;
use App\Enum\MessageType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return list<Message>
     */
    public function findThread(Participant $santa, Participant $target): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.santa = :santa')
            ->andWhere('m.target = :target')
            ->setParameter('santa', $santa)
            ->setParameter('target', $target)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Message>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('s', 't')
            ->join('m.santa', 's')
            ->join('m.target', 't')
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Threads grouped by Santa ↔ target pair, newest activity first.
     *
     * @return list<array{santa: Participant, target: Participant, messages: list<Message>, unreadCount: int}>
     */
    public function findGroupedThreads(): array
    {
        $messages = $this->createQueryBuilder('m')
            ->addSelect('s', 't')
            ->join('m.santa', 's')
            ->join('m.target', 't')
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var array<string, array{santa: Participant, target: Participant, messages: list<Message>, unreadCount: int, lastAt: \DateTimeImmutable}> $groups */
        $groups = [];

        foreach ($messages as $message) {
            $key = $message->getSanta()->getId().'-'.$message->getTarget()->getId();
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'santa' => $message->getSanta(),
                    'target' => $message->getTarget(),
                    'messages' => [],
                    'unreadCount' => 0,
                    'lastAt' => $message->getCreatedAt(),
                ];
            }

            $groups[$key]['messages'][] = $message;
            $groups[$key]['lastAt'] = $message->getCreatedAt();
            if (!$message->isRead()) {
                ++$groups[$key]['unreadCount'];
            }
        }

        uasort($groups, static fn (array $a, array $b): int => $b['lastAt'] <=> $a['lastAt']);

        return array_values(array_map(
            static fn (array $group): array => [
                'santa' => $group['santa'],
                'target' => $group['target'],
                'messages' => $group['messages'],
                'unreadCount' => $group['unreadCount'],
            ],
            $groups
        ));
    }

    public function countUnread(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.isRead = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Unread messages visible to the participant:
     * - fromTarget: replies from their gift target (type ToSanta)
     * - fromSanta: messages from their anonymous Santa (type ToTarget)
     *
     * @return array{fromTarget: int, fromSanta: int}
     */
    public function countUnreadForParticipant(Participant $participant): array
    {
        $fromTarget = 0;
        $fromSanta = 0;

        $asSanta = $participant->getAssignmentAsSanta();
        if ($asSanta?->getTarget() !== null) {
            $fromTarget = (int) $this->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->andWhere('m.santa = :participant')
                ->andWhere('m.target = :target')
                ->andWhere('m.type = :type')
                ->andWhere('m.isRead = false')
                ->setParameter('participant', $participant)
                ->setParameter('target', $asSanta->getTarget())
                ->setParameter('type', MessageType::ToSanta)
                ->getQuery()
                ->getSingleScalarResult();
        }

        $asTarget = $participant->getAssignmentAsTarget();
        if ($asTarget?->getSanta() !== null) {
            $fromSanta = (int) $this->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->andWhere('m.santa = :santa')
                ->andWhere('m.target = :participant')
                ->andWhere('m.type = :type')
                ->andWhere('m.isRead = false')
                ->setParameter('santa', $asTarget->getSanta())
                ->setParameter('participant', $participant)
                ->setParameter('type', MessageType::ToTarget)
                ->getQuery()
                ->getSingleScalarResult();
        }

        return [
            'fromTarget' => $fromTarget,
            'fromSanta' => $fromSanta,
        ];
    }
}
