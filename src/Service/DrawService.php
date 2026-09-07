<?php

namespace App\Service;

use App\Entity\Assignment;
use App\Entity\Participant;
use App\Exception\DrawException;
use App\Repository\AssignmentRepository;
use App\Repository\ExclusionRepository;
use App\Repository\MessageRepository;
use App\Repository\ParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DrawService
{
    public function __construct(
        private readonly ParticipantRepository $participantRepository,
        private readonly ExclusionRepository $exclusionRepository,
        private readonly AssignmentRepository $assignmentRepository,
        private readonly MessageRepository $messageRepository,
        private readonly EntityManagerInterface $em,
        private readonly DrawAlgorithm $drawAlgorithm,
        private readonly AppMailer $appMailer,
    ) {
    }

    /**
     * @return list<Assignment>
     */
    public function run(bool $sendEmails = true): array
    {
        if ($this->assignmentRepository->hasActiveDraw()) {
            throw new DrawException('Un tirage est déjà actif. Réinitialisez-le avant d’en lancer un nouveau.');
        }

        $participants = $this->participantRepository->findAllOrderedByName();
        $ids = [];
        $byId = [];
        foreach ($participants as $participant) {
            $id = $participant->getId();
            if ($id === null) {
                continue;
            }
            $ids[] = $id;
            $byId[$id] = $participant;
        }

        $pairs = $this->drawAlgorithm->draw($ids, $this->exclusionRepository->getForbiddenTargetsMap());
        $assignments = [];

        foreach ($pairs as $santaId => $targetId) {
            $assignment = (new Assignment())
                ->setSanta($byId[$santaId])
                ->setTarget($byId[$targetId]);
            $this->em->persist($assignment);
            $assignments[] = $assignment;
        }

        $this->em->flush();

        if ($sendEmails) {
            foreach ($assignments as $assignment) {
                $this->appMailer->sendDrawResult($assignment);
            }
        }

        return $assignments;
    }

    public function reset(): void
    {
        foreach ($this->messageRepository->findAll() as $message) {
            $this->em->remove($message);
        }
        foreach ($this->assignmentRepository->findAll() as $assignment) {
            $santa = $assignment->getSanta();
            $target = $assignment->getTarget();
            if ($santa instanceof Participant) {
                $santa->setAssignmentAsSanta(null);
            }
            if ($target instanceof Participant) {
                $target->setAssignmentAsTarget(null);
            }
            $this->em->remove($assignment);
        }
        $this->em->flush();
    }
}
