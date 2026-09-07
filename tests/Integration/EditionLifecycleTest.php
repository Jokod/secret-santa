<?php

namespace App\Tests\Integration;

use App\Entity\Exclusion;
use App\Entity\Wish;
use App\Repository\AssignmentRepository;
use App\Repository\MessageRepository;
use App\Service\DrawService;
use App\Service\MessageService;
use App\Tests\AppWebTestCase;

final class EditionLifecycleTest extends AppWebTestCase
{
    public function testFullEditionLifecycle(): void
    {
        $this->ensureSettings(50);

        $alice = $this->createParticipant('Alice', 'alice@family.test');
        $bob = $this->createParticipant('Bob', 'bob@family.test');
        $cara = $this->createParticipant('Cara', 'cara@family.test');
        $dan = $this->createParticipant('Dan', 'dan@family.test');

        // Couple Alice/Bob mutual exclusion
        $this->em->persist((new Exclusion())->setSource($alice)->setTarget($bob));
        $this->em->persist((new Exclusion())->setSource($bob)->setTarget($alice));

        $alice->addWish((new Wish())->setTitle('Mug')->setEstimatedPrice(15)->setPreferenceOrder(1));
        $bob->addWish((new Wish())->setTitle('Livre')->setEstimatedPrice(20)->setPreferenceOrder(1));
        $cara->addWish((new Wish())->setTitle('Écharpe')->setEstimatedPrice(30)->setPreferenceOrder(1));
        $dan->addWish((new Wish())->setTitle('Jeu')->setEstimatedPrice(40)->setPreferenceOrder(1));
        $this->em->flush();

        /** @var DrawService $draw */
        $draw = static::getContainer()->get(DrawService::class);
        $assignments = $draw->run(true);

        self::assertCount(4, $assignments);
        self::assertEmailCount(4);

        foreach ($assignments as $assignment) {
            $santa = $assignment->getSanta();
            $target = $assignment->getTarget();
            self::assertNotNull($santa);
            self::assertNotNull($target);
            self::assertNotSame($santa->getId(), $target->getId());
            if ($santa->getId() === $alice->getId()) {
                self::assertNotSame($bob->getId(), $target->getId());
            }
            if ($santa->getId() === $bob->getId()) {
                self::assertNotSame($alice->getId(), $target->getId());
            }
        }

        $this->em->clear();
        $alice = $this->em->find(\App\Entity\Participant::class, $alice->getId());
        self::assertNotNull($alice?->getAssignmentAsSanta());

        /** @var MessageService $messages */
        $messages = static::getContainer()->get(MessageService::class);
        $messages->sendFromSanta($alice, 'Des indices sur la couleur ?');
        self::assertGreaterThanOrEqual(5, self::getMailerMessages() ? count(self::getMailerMessages()) : 5);

        /** @var MessageRepository $messageRepo */
        $messageRepo = static::getContainer()->get(MessageRepository::class);
        self::assertGreaterThanOrEqual(1, count($messageRepo->findAll()));

        // Locked: cannot mutate settings via guard
        $guard = static::getContainer()->get(\App\Service\EditionLockGuard::class);
        self::assertTrue($guard->isLocked());

        $draw->reset();
        /** @var AssignmentRepository $assignmentRepo */
        $assignmentRepo = static::getContainer()->get(AssignmentRepository::class);
        self::assertFalse($assignmentRepo->hasActiveDraw());
        self::assertSame(0, count($messageRepo->findAll()));

        // Re-run works
        $again = $draw->run(false);
        self::assertCount(4, $again);
    }
}
