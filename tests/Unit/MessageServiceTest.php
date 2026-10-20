<?php

namespace App\Tests\Unit;

use App\Entity\Assignment;
use App\Entity\Message;
use App\Entity\Participant;
use App\Enum\MessageType;
use App\Exception\DrawException;
use App\Message\NotifyUnreadChatMessage;
use App\Service\ChatLivePublisher;
use App\Service\ChatThreadTopic;
use App\Service\MessageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class MessageServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MessageBusInterface&MockObject $bus;
    /** @var list<Update> */
    private array $publishedUpdates = [];
    private MessageService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->publishedUpdates = [];

        $hub = new MockHub(
            'http://hub.test/.well-known/mercure',
            new StaticTokenProvider('token'),
            function (Update $update): string {
                $this->publishedUpdates[] = $update;

                return 'ok';
            },
        );

        $this->service = new MessageService(
            $this->em,
            $this->bus,
            new ChatLivePublisher($hub, new ChatThreadTopic('secret'), new NullLogger()),
        );
    }

    public function testSendFromSantaSuccessSchedulesDelayedNotification(): void
    {
        $santa = $this->participant('Alice', 1);
        $target = $this->participant('Bob', 2);
        (new Assignment())->setSanta($santa)->setTarget($target);

        $this->em->expects(self::once())->method('persist')->with(self::callback(function (Message $message): bool {
            (new \ReflectionProperty(Message::class, 'id'))->setValue($message, 42);

            return true;
        }));
        $this->em->expects(self::once())->method('flush');
        $this->bus->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static fn (object $msg): bool => $msg instanceof NotifyUnreadChatMessage && $msg->messageId === 42),
                self::callback(static function (array $stamps): bool {
                    return isset($stamps[0])
                        && $stamps[0] instanceof DelayStamp
                        && $stamps[0]->getDelay() === MessageService::UNREAD_NOTIFY_DELAY_MS;
                }),
            )
            ->willReturnCallback(static fn (object $msg, array $stamps = []): Envelope => new Envelope($msg, $stamps));

        $message = $this->service->sendFromSanta($santa, '  Bonjour cible  ');

        self::assertSame(MessageType::ToTarget, $message->getType());
        self::assertSame($santa, $message->getSanta());
        self::assertSame($target, $message->getTarget());
        self::assertSame('Bonjour cible', $message->getBody());
        self::assertCount(1, $this->publishedUpdates);
    }

    public function testSendFromSantaThrowsWithoutAssignment(): void
    {
        $santa = $this->participant('Alice');
        $this->em->expects(self::never())->method('persist');
        $this->bus->expects(self::never())->method('dispatch');

        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('Aucun tirage actif');

        $this->service->sendFromSanta($santa, 'Hello');
    }

    public function testSendFromSantaThrowsWhenTargetMissing(): void
    {
        $santa = $this->participant('Alice');
        (new Assignment())->setSanta($santa);
        $this->em->expects(self::never())->method('persist');
        $this->bus->expects(self::never())->method('dispatch');

        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('Aucun tirage actif');

        $this->service->sendFromSanta($santa, 'Hello');
    }

    public function testSendFromTargetSuccess(): void
    {
        $santa = $this->participant('Alice', 3);
        $target = $this->participant('Bob', 4);
        (new Assignment())->setSanta($santa)->setTarget($target);

        $this->em->expects(self::once())->method('persist')->with(self::callback(function (Message $message): bool {
            (new \ReflectionProperty(Message::class, 'id'))->setValue($message, 7);

            return true;
        }));
        $this->em->expects(self::once())->method('flush');
        $this->bus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $msg, array $stamps = []): Envelope => new Envelope($msg, $stamps));

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
        $this->bus->expects(self::never())->method('dispatch');

        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('Aucun tirage actif');

        $this->service->sendFromTarget($target, 'Hello');
    }

    public function testSendFromTargetThrowsWhenSantaMissing(): void
    {
        $target = $this->participant('Bob');
        (new Assignment())->setTarget($target);
        $this->em->expects(self::never())->method('persist');
        $this->bus->expects(self::never())->method('dispatch');

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
        $this->bus->expects(self::never())->method('dispatch');

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
        $this->bus->expects(self::never())->method('dispatch');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage((string) Message::MAX_LENGTH);

        $this->service->sendFromSanta($santa, str_repeat('a', Message::MAX_LENGTH + 1));
    }

    public function testDoesNotScheduleWhenMessageHasNoId(): void
    {
        $santa = $this->participant('Alice');
        $target = $this->participant('Bob');
        (new Assignment())->setSanta($santa)->setTarget($target);

        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');
        $this->bus->expects(self::never())->method('dispatch');

        $this->service->sendFromSanta($santa, 'Sans id');
        self::assertSame([], $this->publishedUpdates);
    }

    public function testMarkThreadReadForViewerMarksOnlyIncoming(): void
    {
        $santa = $this->participant('Alice', 10);
        $target = $this->participant('Bob', 11);
        $toTarget = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('from santa')
            ->setIsRead(false);
        $toSanta = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToSanta)
            ->setBody('from target')
            ->setIsRead(false);

        $this->em->expects(self::once())->method('flush');

        $this->service->markThreadReadForViewer([$toTarget, $toSanta], $target);

        self::assertTrue($toTarget->isRead());
        self::assertFalse($toSanta->isRead());
    }

    public function testMarkThreadReadForViewerNoopWhenNothingToMark(): void
    {
        $santa = $this->participant('Alice', 12);
        $target = $this->participant('Bob', 13);
        $already = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('x')
            ->setIsRead(true);

        $this->em->expects(self::never())->method('flush');

        $this->service->markThreadReadForViewer([$already], $target);
        self::assertTrue($already->isRead());
    }

    public function testMarkReadForRecipientSuccess(): void
    {
        $santa = $this->participant('Alice', 14);
        $target = $this->participant('Bob', 15);
        $message = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToSanta)
            ->setBody('hi')
            ->setIsRead(false);

        $this->em->expects(self::once())->method('flush');

        self::assertTrue($this->service->markReadForRecipient($message, $santa));
        self::assertTrue($message->isRead());
    }

    public function testMarkReadForRecipientRejectsNonRecipient(): void
    {
        $santa = $this->participant('Alice', 16);
        $target = $this->participant('Bob', 17);
        $message = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('hi')
            ->setIsRead(false);

        $this->em->expects(self::never())->method('flush');

        self::assertFalse($this->service->markReadForRecipient($message, $santa));
        self::assertFalse($message->isRead());
    }

    public function testMarkReadForRecipientIdempotentWhenAlreadyRead(): void
    {
        $santa = $this->participant('Alice', 18);
        $target = $this->participant('Bob', 19);
        $message = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('hi')
            ->setIsRead(true);

        $this->em->expects(self::never())->method('flush');

        self::assertTrue($this->service->markReadForRecipient($message, $target));
        self::assertTrue($message->isRead());
    }

    private function participant(string $name, ?int $id = null): Participant
    {
        $participant = (new Participant())
            ->setName($name)
            ->setEmail(strtolower($name).'@test.com')
            ->setTokenSecret('tok-'.strtolower($name));
        if ($id !== null) {
            (new \ReflectionProperty(Participant::class, 'id'))->setValue($participant, $id);
        }

        return $participant;
    }
}
