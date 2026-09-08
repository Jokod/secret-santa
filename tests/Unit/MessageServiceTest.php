<?php

namespace App\Tests\Unit;

use App\Entity\Assignment;
use App\Entity\EditionSettings;
use App\Entity\Message;
use App\Entity\Participant;
use App\Enum\MessageType;
use App\Exception\DrawException;
use App\Repository\EditionSettingsRepository;
use App\Service\AppMailer;
use App\Service\MessageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MessageServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MailerInterface&MockObject $mailer;
    private MessageService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);

        $settingsRepo = $this->createStub(EditionSettingsRepository::class);
        $settingsRepo->method('getSettings')->willReturn(new EditionSettings());
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://santa.test/p/token');

        $this->service = new MessageService(
            $this->em,
            new AppMailer($this->mailer, $urlGenerator, $settingsRepo, 'noreply@santa.test'),
        );
    }

    public function testSendFromSantaSuccess(): void
    {
        $santa = $this->participant('Alice');
        $target = $this->participant('Bob');
        (new Assignment())->setSanta($santa)->setTarget($target);

        $this->em->expects(self::once())->method('persist')->with(self::isInstanceOf(Message::class));
        $this->em->expects(self::once())->method('flush');
        $this->mailer->expects(self::once())->method('send');

        $message = $this->service->sendFromSanta($santa, '  Bonjour cible  ');

        self::assertSame(MessageType::ToTarget, $message->getType());
        self::assertSame($santa, $message->getSanta());
        self::assertSame($target, $message->getTarget());
        self::assertSame('Bonjour cible', $message->getBody());
    }

    public function testSendFromSantaThrowsWithoutAssignment(): void
    {
        $santa = $this->participant('Alice');
        $this->em->expects(self::never())->method('persist');
        $this->mailer->expects(self::never())->method('send');

        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('Aucun tirage actif');

        $this->service->sendFromSanta($santa, 'Hello');
    }

    public function testSendFromSantaThrowsWhenTargetMissing(): void
    {
        $santa = $this->participant('Alice');
        (new Assignment())->setSanta($santa);
        $this->em->expects(self::never())->method('persist');
        $this->mailer->expects(self::never())->method('send');

        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('Aucun tirage actif');

        $this->service->sendFromSanta($santa, 'Hello');
    }

    public function testSendFromTargetSuccess(): void
    {
        $santa = $this->participant('Alice');
        $target = $this->participant('Bob');
        (new Assignment())->setSanta($santa)->setTarget($target);

        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');
        $this->mailer->expects(self::once())->method('send');

        $message = $this->service->sendFromTarget($target, 'Merci Santa');

        self::assertSame(MessageType::ToSanta, $message->getType());
        self::assertSame($santa, $message->getSanta());
        self::assertSame($target, $message->getTarget());
        self::assertSame('Merci Santa', $message->getBody());
    }

    public function testSendFromTargetThrowsWithoutAssignment(): void
    {
        $target = $this->participant('Bob');
        $this->em->expects(self::never())->method('persist');
        $this->mailer->expects(self::never())->method('send');

        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('Aucun tirage actif');

        $this->service->sendFromTarget($target, 'Hello');
    }

    public function testSendFromTargetThrowsWhenSantaMissing(): void
    {
        $target = $this->participant('Bob');
        (new Assignment())->setTarget($target);
        $this->em->expects(self::never())->method('persist');
        $this->mailer->expects(self::never())->method('send');

        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('Aucun tirage actif');

        $this->service->sendFromTarget($target, 'Hello');
    }

    public function testRejectsEmptyBody(): void
    {
        $santa = $this->participant('Alice');
        $target = $this->participant('Bob');
        (new Assignment())->setSanta($santa)->setTarget($target);
        $this->em->expects(self::never())->method('persist');
        $this->mailer->expects(self::never())->method('send');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('vide');

        $this->service->sendFromSanta($santa, '   ');
    }

    public function testRejectsTooLongBody(): void
    {
        $santa = $this->participant('Alice');
        $target = $this->participant('Bob');
        (new Assignment())->setSanta($santa)->setTarget($target);
        $this->em->expects(self::never())->method('persist');
        $this->mailer->expects(self::never())->method('send');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage((string) Message::MAX_LENGTH);

        $this->service->sendFromSanta($santa, str_repeat('a', Message::MAX_LENGTH + 1));
    }

    private function participant(string $name): Participant
    {
        return (new Participant())
            ->setName($name)
            ->setEmail(strtolower($name).'@test.com')
            ->setTokenSecret('tok-'.strtolower($name));
    }
}
