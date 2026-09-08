<?php

namespace App\Tests\Unit;

use App\Entity\Assignment;
use App\Entity\EditionSettings;
use App\Entity\Message;
use App\Entity\Participant;
use App\Exception\DrawException;
use App\Repository\AssignmentRepository;
use App\Repository\EditionSettingsRepository;
use App\Repository\ExclusionRepository;
use App\Repository\MessageRepository;
use App\Repository\ParticipantRepository;
use App\Service\AppMailer;
use App\Service\DrawAlgorithm;
use App\Service\DrawService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DrawServiceTest extends TestCase
{
    /**
     * @param ParticipantRepository&MockObject|ParticipantRepository&Stub $participantRepository
     * @param ExclusionRepository&MockObject|ExclusionRepository&Stub $exclusionRepository
     * @param AssignmentRepository&MockObject|AssignmentRepository&Stub $assignmentRepository
     * @param MessageRepository&MockObject|MessageRepository&Stub $messageRepository
     * @param EntityManagerInterface&MockObject|EntityManagerInterface&Stub $em
     * @param MailerInterface&MockObject|MailerInterface&Stub $mailer
     */
    private function createService(
        ParticipantRepository $participantRepository,
        ExclusionRepository $exclusionRepository,
        AssignmentRepository $assignmentRepository,
        MessageRepository $messageRepository,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): DrawService {
        $settingsRepo = $this->createStub(EditionSettingsRepository::class);
        $settingsRepo->method('getSettings')->willReturn(new EditionSettings());
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://santa.test/p/token');

        return new DrawService(
            $participantRepository,
            $exclusionRepository,
            $assignmentRepository,
            $messageRepository,
            $em,
            new DrawAlgorithm(seed: 42),
            new AppMailer($mailer, $urlGenerator, $settingsRepo, 'noreply@santa.test'),
        );
    }

    public function testRunThrowsWhenActiveDrawExists(): void
    {
        $assignmentRepository = $this->createMock(AssignmentRepository::class);
        $assignmentRepository->expects(self::once())->method('hasActiveDraw')->willReturn(true);

        $participantRepository = $this->createMock(ParticipantRepository::class);
        $participantRepository->expects(self::never())->method('findAllOrderedByName');

        $service = $this->createService(
            $participantRepository,
            $this->createStub(ExclusionRepository::class),
            $assignmentRepository,
            $this->createStub(MessageRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(MailerInterface::class),
        );

        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('déjà actif');

        $service->run();
    }

    public function testRunSkipsParticipantsWithNullId(): void
    {
        $withId = $this->participantWithId(1, 'Alice');
        $withoutId = (new Participant())->setName('Ghost')->setEmail('ghost@test.com')->setTokenSecret('g');
        $bob = $this->participantWithId(2, 'Bob');
        $cara = $this->participantWithId(3, 'Cara');

        $assignmentRepository = $this->createMock(AssignmentRepository::class);
        $assignmentRepository->expects(self::once())->method('hasActiveDraw')->willReturn(false);

        $participantRepository = $this->createMock(ParticipantRepository::class);
        $participantRepository->expects(self::once())->method('findAllOrderedByName')
            ->willReturn([$withId, $withoutId, $bob, $cara]);

        $exclusionRepository = $this->createMock(ExclusionRepository::class);
        $exclusionRepository->expects(self::once())->method('getForbiddenTargetsMap')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(3))->method('persist')->with(self::isInstanceOf(Assignment::class));
        $em->expects(self::once())->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(3))->method('send');

        $service = $this->createService(
            $participantRepository,
            $exclusionRepository,
            $assignmentRepository,
            $this->createStub(MessageRepository::class),
            $em,
            $mailer,
        );

        $assignments = $service->run(true);

        self::assertCount(3, $assignments);
        foreach ($assignments as $assignment) {
            self::assertInstanceOf(Assignment::class, $assignment);
            self::assertNotNull($assignment->getSanta());
            self::assertNotNull($assignment->getTarget());
            self::assertNotSame($assignment->getSanta(), $assignment->getTarget());
        }
    }

    public function testRunWithoutSendingEmails(): void
    {
        $a = $this->participantWithId(1, 'Alice');
        $b = $this->participantWithId(2, 'Bob');
        $c = $this->participantWithId(3, 'Cara');

        $assignmentRepository = $this->createMock(AssignmentRepository::class);
        $assignmentRepository->expects(self::once())->method('hasActiveDraw')->willReturn(false);

        $participantRepository = $this->createMock(ParticipantRepository::class);
        $participantRepository->expects(self::once())->method('findAllOrderedByName')->willReturn([$a, $b, $c]);

        $exclusionRepository = $this->createMock(ExclusionRepository::class);
        $exclusionRepository->expects(self::once())->method('getForbiddenTargetsMap')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(3))->method('persist');
        $em->expects(self::once())->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $service = $this->createService(
            $participantRepository,
            $exclusionRepository,
            $assignmentRepository,
            $this->createStub(MessageRepository::class),
            $em,
            $mailer,
        );

        self::assertCount(3, $service->run(false));
    }

    public function testResetClearsMessagesAndAssignments(): void
    {
        $santa = $this->participantWithId(1, 'Alice');
        $target = $this->participantWithId(2, 'Bob');
        $assignment = (new Assignment())->setSanta($santa)->setTarget($target);
        $message = new Message();

        $messageRepository = $this->createMock(MessageRepository::class);
        $messageRepository->expects(self::once())->method('findAll')->willReturn([$message]);

        $assignmentRepository = $this->createMock(AssignmentRepository::class);
        $assignmentRepository->expects(self::once())->method('findAll')->willReturn([$assignment]);

        $removed = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))
            ->method('remove')
            ->willReturnCallback(static function (object $entity) use (&$removed): void {
                $removed[] = $entity;
            });
        $em->expects(self::once())->method('flush');

        $service = $this->createService(
            $this->createStub(ParticipantRepository::class),
            $this->createStub(ExclusionRepository::class),
            $assignmentRepository,
            $messageRepository,
            $em,
            $this->createStub(MailerInterface::class),
        );

        $service->reset();

        self::assertNull($santa->getAssignmentAsSanta());
        self::assertNull($target->getAssignmentAsTarget());
        self::assertContains($message, $removed);
        self::assertContains($assignment, $removed);
    }

    public function testResetHandlesAssignmentsWithoutParticipants(): void
    {
        $orphan = new Assignment();

        $messageRepository = $this->createMock(MessageRepository::class);
        $messageRepository->expects(self::once())->method('findAll')->willReturn([]);

        $assignmentRepository = $this->createMock(AssignmentRepository::class);
        $assignmentRepository->expects(self::once())->method('findAll')->willReturn([$orphan]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($orphan);
        $em->expects(self::once())->method('flush');

        $service = $this->createService(
            $this->createStub(ParticipantRepository::class),
            $this->createStub(ExclusionRepository::class),
            $assignmentRepository,
            $messageRepository,
            $em,
            $this->createStub(MailerInterface::class),
        );

        $service->reset();
    }

    private function participantWithId(int $id, string $name): Participant
    {
        $participant = (new Participant())
            ->setName($name)
            ->setEmail(strtolower($name).'@test.com')
            ->setTokenSecret('token-'.$id);

        $ref = new \ReflectionProperty(Participant::class, 'id');
        $ref->setValue($participant, $id);

        return $participant;
    }
}
