<?php

namespace App\Tests\Functional;

use App\Entity\Message;
use App\Enum\MessageType;
use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MarkMessagesUnreadCommandTest extends AppWebTestCase
{
    public function testMarksAllMessagesUnread(): void
    {
        $this->ensureSettings();
        $a = $this->createParticipant('UnreadCmdA', 'unreadcmda@example.com');
        $b = $this->createParticipant('UnreadCmdB', 'unreadcmdb@example.com');

        $read = (new Message())
            ->setSanta($a)
            ->setTarget($b)
            ->setType(MessageType::ToTarget)
            ->setBody('Deja lu')
            ->setIsRead(true);
        $unread = (new Message())
            ->setSanta($a)
            ->setTarget($b)
            ->setType(MessageType::ToSanta)
            ->setBody('Deja non lu')
            ->setIsRead(false);
        $this->em->persist($read);
        $this->em->persist($unread);
        $this->em->flush();
        $readId = $read->getId();
        $unreadId = $unread->getId();

        $application = new Application(self::$kernel);
        $command = $application->find('app:messages:mark-unread');
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('1 message(s) passé(s) en non lu.', $tester->getDisplay());

        $this->em->clear();
        self::assertFalse($this->em->find(Message::class, $readId)?->isRead());
        self::assertFalse($this->em->find(Message::class, $unreadId)?->isRead());
    }
}
