<?php

namespace App\Tests\Functional;

use App\Entity\Assignment;
use App\Entity\Message;
use App\Exception\DrawException;
use App\Service\DrawService;
use App\Tests\AppWebTestCase;

final class DrawAndMessagingTest extends AppWebTestCase
{
    public function testDrawUiBlocksWhenFewerThanThreeParticipants(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $this->createParticipant('Only', 'only@example.com');

        $this->client->request('GET', '/admin/draw');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button.is-disabled');
        self::assertSelectorTextContains('.hint-box.info', 'Il manque');
        self::assertSelectorTextContains('body', 'minimum requis : 3');
    }

    public function testDrawServiceRejectsFewerThanThreeParticipants(): void
    {
        $this->ensureSettings();
        $this->createParticipant('Only', 'only-svc@example.com');

        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('3 participants');

        static::getContainer()->get(DrawService::class)->run(false);
    }

    public function testDrawSucceedsAndSendsEmails(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $this->createParticipant('A', 'a@ex.com');
        $this->createParticipant('B', 'b@ex.com');
        $this->createParticipant('C', 'c@ex.com');

        $crawler = $this->client->request('GET', '/admin/draw');
        $this->client->submit($crawler->selectButton('Lancer le tirage')->form([
            'confirm' => '1',
            'confirm_missing_wishes' => '1',
        ]));
        self::assertResponseRedirects('/admin/draw');
        self::assertEmailCount(3);

        $this->client->followRedirect();
        self::assertSelectorTextContains('h2', 'Appariements');
        self::assertSelectorExists('table.data');
    }

    public function testResetClearsAssignmentsAndMessages(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $a = $this->createParticipant('A', 'a3@ex.com');
        $this->createParticipant('B', 'b3@ex.com');
        $this->createParticipant('C', 'c3@ex.com');

        $crawler = $this->client->request('GET', '/admin/draw');
        $this->client->submit($crawler->selectButton('Lancer le tirage')->form([
            'confirm' => '1',
            'confirm_missing_wishes' => '1',
        ]));

        $this->em->clear();
        $a = $this->em->find(\App\Entity\Participant::class, $a->getId());
        self::assertNotNull($a?->getAssignmentAsSanta());

        $message = (new Message())
            ->setSanta($a)
            ->setTarget($a->getAssignmentAsSanta()->getTarget())
            ->setBody('Hello');
        $this->em->persist($message);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/draw');
        $form = $crawler->selectButton('Réinitialiser')->form([
            'confirm' => '1',
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');

        $this->em->clear();
        self::assertSame(0, $this->em->getRepository(Assignment::class)->count([]));
        self::assertSame(0, $this->em->getRepository(Message::class)->count([]));
    }

    public function testParticipantMessagingIsAnonymous(): void
    {
        $this->ensureSettings();
        $a = $this->createParticipant('Ana', 'ana@ex.com');
        $this->createParticipant('Bea', 'bea@ex.com');
        $this->createParticipant('Cid', 'cid@ex.com');

        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin/draw');
        $this->client->submit($crawler->selectButton('Lancer le tirage')->form([
            'confirm' => '1',
            'confirm_missing_wishes' => '1',
        ]));

        $this->em->clear();
        $a = $this->em->find(\App\Entity\Participant::class, $a->getId());
        self::assertNotNull($a);

        $crawler = $this->client->request('GET', '/participant/'.$a->getTokenSecret());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Tu offres à');
        self::assertSelectorExists('.messaging-card');

        $crawler = $this->client->request('GET', '/participant/'.$a->getTokenSecret().'/messages/target');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Écrire à ma cible')->form([
            'message_form[body]' => 'Quelle taille portes-tu ?',
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.chat-bubble', 'Quelle taille portes-tu');
        self::assertSelectorTextContains('.chat-bubble-meta', 'Toi (Santa)');
        self::assertSelectorTextNotContains('.chat', 'Ana');
    }
}
