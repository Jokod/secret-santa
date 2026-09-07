<?php

namespace App\Tests\Functional;

use App\Entity\Assignment;
use App\Entity\Message;
use App\Enum\MessageType;
use App\Tests\AppWebTestCase;

final class MessageControllerCoverageTest extends AppWebTestCase
{
    public function testListShowsConversationCards(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $a = $this->createParticipant('MsgA', 'msga@example.com');
        $b = $this->createParticipant('MsgB', 'msgb@example.com');
        $c = $this->createParticipant('MsgC', 'msgc@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($c));
        $this->em->persist((new Assignment())->setSanta($c)->setTarget($a));

        $unread = (new Message())
            ->setSanta($a)
            ->setTarget($b)
            ->setType(MessageType::ToTarget)
            ->setBody('Message non lu pour orga')
            ->setIsRead(false);
        $alreadyRead = (new Message())
            ->setSanta($b)
            ->setTarget($c)
            ->setType(MessageType::ToSanta)
            ->setBody('Deja lu')
            ->setIsRead(true);
        $this->em->persist($unread);
        $this->em->persist($alreadyRead);
        $this->em->flush();
        $unreadId = $unread->getId();

        $this->client->request('GET', '/admin/messages');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.messaging-card');
        self::assertSelectorTextContains('body', 'Msga');
        self::assertSelectorTextContains('body', 'Msgb');
        self::assertSelectorTextContains('body', 'Message non lu pour orga');
        self::assertSelectorTextContains('body', 'Deja lu');
        self::assertSelectorExists(sprintf('a[href="/admin/messages/%d/%d"]', $a->getId(), $b->getId()));

        $this->em->clear();
        $stillUnread = $this->em->find(Message::class, $unreadId);
        self::assertNotNull($stillUnread);
        self::assertFalse($stillUnread->isRead());

        $this->client->request('GET', '/admin/messages/'.$a->getId().'/'.$b->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.chat-bubble-santa');
        self::assertSelectorTextContains('body', 'Message non lu pour orga');

        $this->em->clear();
        $reloaded = $this->em->find(Message::class, $unreadId);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isRead());
    }

    public function testEmptyMessagesList(): void
    {
        $this->loginAdmin();
        $this->client->request('GET', '/admin/messages');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Aucun message');
    }

    public function testShowUnknownConversationReturns404(): void
    {
        $this->loginAdmin();
        $this->client->request('GET', '/admin/messages/99999/99998');
        self::assertResponseStatusCodeSame(404);
    }

    public function testShowExistingParticipantsWithoutMessagesReturns404(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $a = $this->createParticipant('NoThreadA', 'nothreada@example.com');
        $b = $this->createParticipant('NoThreadB', 'nothreadb@example.com');

        $this->client->request('GET', '/admin/messages/'.$a->getId().'/'.$b->getId());
        self::assertResponseStatusCodeSame(404);
    }
}
