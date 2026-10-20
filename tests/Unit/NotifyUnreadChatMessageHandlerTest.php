<?php

namespace App\Tests\Unit;

use App\Entity\EditionSettings;
use App\Entity\Message;
use App\Entity\Participant;
use App\Enum\MessageType;
use App\Message\NotifyUnreadChatMessage;
use App\MessageHandler\NotifyUnreadChatMessageHandler;
use App\Repository\EditionSettingsRepository;
use App\Repository\MessageRepository;
use App\Service\AppMailer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NotifyUnreadChatMessageHandlerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MessageRepository&MockObject $messageRepository;
    private MailerInterface&MockObject $mailer;
    private NotifyUnreadChatMessageHandler $handler;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);

        $settingsRepo = $this->createStub(EditionSettingsRepository::class);
        $settingsRepo->method('getSettings')->willReturn(new EditionSettings());
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/participant/tok/messages/santa');

        $this->handler = new NotifyUnreadChatMessageHandler(
            $this->em,
            $this->messageRepository,
            new AppMailer($this->mailer, $urlGenerator, $settingsRepo, 'noreply@santa.test', 'https://santa.test'),
        );
    }

    public function testSendsDigestAndMarksNotified(): void
    {
        $santa = $this->participant('Alice', 'alice@test.com', 1);
        $target = $this->participant('Bob', 'bob@test.com', 2);
        $message = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('Hello')
            ->setIsRead(false);
        $sibling = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('Again')
            ->setIsRead(false);

        $this->em->expects(self::once())->method('find')->with(Message::class, 9)->willReturn($message);
        $this->messageRepository->expects(self::once())
            ->method('findUnnotifiedUnreadForRecipientInThread')
            ->with($santa, $target, $target)
            ->willReturn([$message, $sibling]);
        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (Email $email) use ($target): bool {
                return $email->getTo()[0]->getAddress() === $target->getEmail()
                    && $email->getSubject() === '2 nouveaux messages Secret Santa';
            }));
        $this->em->expects(self::once())->method('flush');

        ($this->handler)(new NotifyUnreadChatMessage(9));

        self::assertNotNull($message->getEmailNotifiedAt());
        self::assertNotNull($sibling->getEmailNotifiedAt());
    }

    public function testSkipsWhenAlreadyNotified(): void
    {
        $message = (new Message())
            ->setSanta($this->participant('A', 'a@t.com', 1))
            ->setTarget($this->participant('B', 'b@t.com', 2))
            ->setType(MessageType::ToTarget)
            ->setBody('x')
            ->setIsRead(false)
            ->setEmailNotifiedAt(new \DateTimeImmutable());

        $this->em->expects(self::once())->method('find')->willReturn($message);
        $this->messageRepository->expects(self::never())->method('findUnnotifiedUnreadForRecipientInThread');
        $this->mailer->expects(self::never())->method('send');

        ($this->handler)(new NotifyUnreadChatMessage(1));
    }

    public function testSkipsWhenAlreadyRead(): void
    {
        $message = (new Message())
            ->setSanta($this->participant('A', 'a@t.com', 1))
            ->setTarget($this->participant('B', 'b@t.com', 2))
            ->setType(MessageType::ToTarget)
            ->setBody('x')
            ->setIsRead(true);

        $this->em->expects(self::once())->method('find')->willReturn($message);
        $this->mailer->expects(self::never())->method('send');

        ($this->handler)(new NotifyUnreadChatMessage(1));
    }

    public function testSkipsWhenMessageMissing(): void
    {
        $this->em->expects(self::once())->method('find')->willReturn(null);
        $this->mailer->expects(self::never())->method('send');

        ($this->handler)(new NotifyUnreadChatMessage(99));
    }

    public function testSkipsWhenBatchEmpty(): void
    {
        $santa = $this->participant('Alice', 'alice@test.com', 1);
        $target = $this->participant('Bob', 'bob@test.com', 2);
        $message = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToSanta)
            ->setBody('Merci')
            ->setIsRead(false);

        $this->em->expects(self::once())->method('find')->willReturn($message);
        $this->messageRepository->expects(self::once())
            ->method('findUnnotifiedUnreadForRecipientInThread')
            ->willReturn([]);
        $this->mailer->expects(self::never())->method('send');
        $this->em->expects(self::never())->method('flush');

        ($this->handler)(new NotifyUnreadChatMessage(3));
    }

    private function participant(string $name, string $email, int $id): Participant
    {
        $p = (new Participant())
            ->setName($name)
            ->setEmail($email)
            ->setTokenSecret(str_repeat('z', 64));
        (new \ReflectionProperty(Participant::class, 'id'))->setValue($p, $id);

        return $p;
    }
}
