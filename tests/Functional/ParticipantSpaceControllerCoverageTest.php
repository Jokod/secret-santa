<?php

namespace App\Tests\Functional;

use App\Entity\Assignment;
use App\Entity\Message;
use App\Entity\Wish;
use App\Enum\MessageType;
use App\Tests\AppWebTestCase;

final class ParticipantSpaceControllerCoverageTest extends AppWebTestCase
{
    public function testAddWishAfterDraw(): void
    {
        $this->ensureSettings(50);
        $a = $this->createParticipant('WishLockA', 'wishlocka@example.com');
        $b = $this->createParticipant('WishLockB', 'wishlockb@example.com');

        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$a->getTokenSecret());
        self::assertSelectorTextContains('.form-section-title', 'Ajouter un souhait');
        $form = $crawler->selectButton('Ajouter un souhait')->form([
            'wish[title]' => 'Toujours ok',
            'wish[estimatedPrice]' => '10',
            'wish[preferenceOrder]' => '1',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/participant/'.$a->getTokenSecret());
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertSelectorTextContains('table', 'Toujours ok');
    }

    public function testAddWishInvalidForm(): void
    {
        $this->ensureSettings(50);
        $p = $this->createParticipant('WishInv', 'wishinv@example.com');

        $crawler = $this->client->request('GET', '/participant/'.$p->getTokenSecret());
        $form = $crawler->selectButton('Ajouter un souhait')->form([
            'wish[title]' => str_repeat('x', 200),
            'wish[estimatedPrice]' => '10',
            'wish[preferenceOrder]' => '1',
        ]);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorNotExists('table.data tbody tr');
    }

    public function testEditWishSuccess(): void
    {
        $this->ensureSettings(50);
        $p = $this->createParticipant('WishEdit', 'wishedit@example.com');
        $wish = (new Wish())->setTitle('Ancien titre')->setEstimatedPrice(10)->setPreferenceOrder(1);
        $p->addWish($wish);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$p->getTokenSecret().'/wishes/'.$wish->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-section-title', 'Modifier le souhait');
        $form = $crawler->selectButton('Enregistrer')->form([
            'wish[title]' => 'Nouveau titre',
            'wish[estimatedPrice]' => '15',
            'wish[preferenceOrder]' => '2',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/participant/'.$p->getTokenSecret());
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertSelectorTextContains('table', 'Nouveau titre');

        $this->em->clear();
        $updated = $this->em->find(Wish::class, $wish->getId());
        self::assertNotNull($updated);
        self::assertSame('Nouveau titre', $updated->getTitle());
        self::assertSame(15.0, $updated->getEstimatedPrice());
        self::assertSame(2, $updated->getPreferenceOrder());
    }

    public function testEditWishWrongOwner(): void
    {
        $this->ensureSettings(50);
        $owner = $this->createParticipant('EditOwner', 'editowner@example.com');
        $other = $this->createParticipant('EditOther', 'editother@example.com');
        $wish = (new Wish())->setTitle('PriveEdit')->setEstimatedPrice(10)->setPreferenceOrder(1);
        $owner->addWish($wish);
        $this->em->flush();

        $this->client->request('GET', '/participant/'.$other->getTokenSecret().'/wishes/'.$wish->getId().'/edit');
        self::assertResponseStatusCodeSame(404);
    }

    public function testEditWishAboveBudget(): void
    {
        $this->ensureSettings(50);
        $p = $this->createParticipant('WishBudgetEdit', 'wishbudgetedit@example.com');
        $wish = (new Wish())->setTitle('Ok')->setEstimatedPrice(10)->setPreferenceOrder(1);
        $p->addWish($wish);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$p->getTokenSecret().'/wishes/'.$wish->getId().'/edit');
        $form = $crawler->selectButton('Enregistrer')->form([
            'wish[title]' => 'Trop cher',
            'wish[estimatedPrice]' => '80',
            'wish[preferenceOrder]' => '1',
        ]);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-section-title', 'Modifier le souhait');

        $this->em->clear();
        $unchanged = $this->em->find(Wish::class, $wish->getId());
        self::assertNotNull($unchanged);
        self::assertSame('Ok', $unchanged->getTitle());
        self::assertSame(10.0, $unchanged->getEstimatedPrice());
    }

    public function testEditWishAfterDraw(): void
    {
        $this->ensureSettings(50);
        $a = $this->createParticipant('EditLockA', 'editlocka@example.com');
        $b = $this->createParticipant('EditLockB', 'editlockb@example.com');
        $wish = (new Wish())->setTitle('Avant')->setEstimatedPrice(10)->setPreferenceOrder(1);
        $a->addWish($wish);
        $this->em->flush();

        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$a->getTokenSecret().'/wishes/'.$wish->getId().'/edit');
        $form = $crawler->selectButton('Enregistrer')->form([
            'wish[title]' => 'Après tirage',
            'wish[estimatedPrice]' => '12',
            'wish[preferenceOrder]' => '1',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/participant/'.$a->getTokenSecret());
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertSelectorTextContains('table', 'Après tirage');
    }

    public function testDeleteWishWrongOwner(): void
    {
        $this->ensureSettings(50);
        $owner = $this->createParticipant('Owner', 'owner@example.com');
        $other = $this->createParticipant('Other', 'other@example.com');
        $wish = (new Wish())->setTitle('Prive')->setEstimatedPrice(10)->setPreferenceOrder(1);
        $owner->addWish($wish);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$owner->getTokenSecret());
        $token = $crawler->filter(sprintf('form[action$="/wishes/%d/delete"] input[name="_token"]', $wish->getId()))->attr('value');

        $this->client->request(
            'POST',
            '/participant/'.$other->getTokenSecret().'/wishes/'.$wish->getId().'/delete',
            ['_token' => $token]
        );
        // AccessDenied → 404 uniforme (pas d'oracle)
        self::assertResponseStatusCodeSame(404);
        self::assertNotNull($this->em->find(Wish::class, $wish->getId()));
    }

    public function testDeleteWishInvalidCsrf(): void
    {
        $this->ensureSettings(50);
        $p = $this->createParticipant('WishCsrf', 'wishcsrf@example.com');
        $wish = (new Wish())->setTitle('Keep')->setEstimatedPrice(10)->setPreferenceOrder(1);
        $p->addWish($wish);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/participant/'.$p->getTokenSecret().'/wishes/'.$wish->getId().'/delete',
            ['_token' => 'invalid']
        );
        self::assertResponseRedirects('/participant/'.$p->getTokenSecret());
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSelectorTextContains('.flash-danger', 'Jeton');
        self::assertNotNull($this->em->find(Wish::class, $wish->getId()));
    }

    public function testDeleteWishAfterDraw(): void
    {
        $this->ensureSettings(50);
        $a = $this->createParticipant('DelLockA', 'dellocka@example.com');
        $b = $this->createParticipant('DelLockB', 'dellockb@example.com');
        $wish = (new Wish())->setTitle('Fige')->setEstimatedPrice(10)->setPreferenceOrder(1);
        $a->addWish($wish);
        $this->em->flush();

        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$a->getTokenSecret());
        $this->client->submit($crawler->selectButton('Supprimer')->form());
        self::assertResponseRedirects('/participant/'.$a->getTokenSecret());
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertNull($this->em->find(Wish::class, $wish->getId()));
    }

    public function testDeleteWishSuccess(): void
    {
        $this->ensureSettings(50);
        $p = $this->createParticipant('WishOk', 'wishok@example.com');
        $wish = (new Wish())->setTitle('A virer')->setEstimatedPrice(10)->setPreferenceOrder(1);
        $p->addWish($wish);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$p->getTokenSecret());
        $this->client->submit($crawler->selectButton('Supprimer')->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertNull($this->em->find(Wish::class, $wish->getId()));
    }

    public function testSendMessageInvalidForm(): void
    {
        $this->ensureSettings();
        $a = $this->createParticipant('MsgInvA', 'msginva@example.com');
        $b = $this->createParticipant('MsgInvB', 'msginvb@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$a->getTokenSecret().'/messages/target');
        $form = $crawler->selectButton('Écrire à ma cible')->form([
            'message_form[body]' => '',
        ]);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Message::class)->count([]));
    }

    public function testSendMessageToSantaInvalidForm(): void
    {
        $this->ensureSettings();
        $a = $this->createParticipant('MsgInvSantaA', 'msginvsantaa@example.com');
        $b = $this->createParticipant('MsgInvSantaB', 'msginvsantab@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$b->getTokenSecret().'/messages/santa');
        $form = $crawler->selectButton('Écrire à mon Santa')->form([
            'message_form[body]' => '',
        ]);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Message::class)->count([]));
    }

    public function testSendMessageToSanta(): void
    {
        $this->ensureSettings();
        $a = $this->createParticipant('ToSantaA', 'tosantaa@example.com');
        $b = $this->createParticipant('ToSantaB', 'tosantab@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$b->getTokenSecret().'/messages/santa');
        $form = $crawler->selectButton('Écrire à mon Santa')->form([
            'message_form[body]' => 'Merci Santa anonyme !',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/participant/'.$b->getTokenSecret().'/messages/santa');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertSelectorTextContains('.chat-bubble', 'Merci Santa anonyme');

        $messages = $this->em->getRepository(Message::class)->findAll();
        self::assertCount(1, $messages);
        self::assertSame(MessageType::ToSanta, $messages[0]->getType());
    }

    public function testSendMessageWithoutDrawShowsDanger(): void
    {
        $this->ensureSettings();
        $p = $this->createParticipant('NoDraw', 'nodraw@example.com');
        $other = $this->createParticipant('NoDraw2', 'nodraw2@example.com');

        $this->em->persist((new Assignment())->setSanta($other)->setTarget($p));
        $this->em->persist((new Assignment())->setSanta($p)->setTarget($other));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$other->getTokenSecret().'/messages/target');
        $token = $crawler->filter('form input[name="message_form[_token]"]')->attr('value');

        foreach ($this->em->getRepository(Assignment::class)->findAll() as $assignment) {
            $this->em->remove($assignment);
        }
        $this->em->flush();
        $this->em->clear();
        $p = $this->em->getRepository(\App\Entity\Participant::class)->findOneBy(['email' => 'nodraw@example.com']);
        self::assertNotNull($p);

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/messages/target', [
            'message_form' => [
                'body' => 'Sans tirage',
                '_token' => $token,
            ],
        ]);
        self::assertResponseRedirects('/participant/'.$p->getTokenSecret());
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
    }

    public function testInvalidTokenReturns404(): void
    {
        $this->client->request('GET', '/participant/'.str_repeat('b', 64));
        self::assertResponseStatusCodeSame(404);
    }

    public function testHomeShowsUnreadAndMessagePagesMarkAsRead(): void
    {
        $this->ensureSettings();
        $santa = $this->createParticipant('ReadSanta', 'readsanta@example.com');
        $target = $this->createParticipant('ReadTarget', 'readtarget@example.com');
        $this->em->persist((new Assignment())->setSanta($santa)->setTarget($target));
        $this->em->persist((new Assignment())->setSanta($target)->setTarget($santa));

        $fromTarget = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToSanta)
            ->setBody('Reponse cible')
            ->setIsRead(false);
        $fromSanta = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('Question santa')
            ->setIsRead(false);
        $this->em->persist($fromTarget);
        $this->em->persist($fromSanta);
        $this->em->flush();
        $fromTargetId = $fromTarget->getId();
        $fromSantaId = $fromSanta->getId();

        $this->client->request('GET', '/participant/'.$santa->getTokenSecret());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.messaging-card.has-unread', '1 non lu');
        $this->em->clear();
        self::assertFalse($this->em->find(Message::class, $fromTargetId)?->isRead());

        $this->client->request('GET', '/participant/'.$santa->getTokenSecret().'/messages/target');
        self::assertResponseIsSuccessful();
        $this->em->clear();
        self::assertTrue($this->em->find(Message::class, $fromTargetId)?->isRead());

        $this->client->request('GET', '/participant/'.$target->getTokenSecret());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.messaging-card.has-unread', '1 non lu');
        $this->em->clear();
        self::assertFalse($this->em->find(Message::class, $fromSantaId)?->isRead());

        $this->client->request('GET', '/participant/'.$target->getTokenSecret().'/messages/santa');
        self::assertResponseIsSuccessful();
        $this->em->clear();
        self::assertTrue($this->em->find(Message::class, $fromSantaId)?->isRead());
    }
}
