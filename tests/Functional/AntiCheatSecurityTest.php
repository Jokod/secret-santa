<?php

namespace App\Tests\Functional;

use App\Entity\Assignment;
use App\Entity\Message;
use App\Entity\Wish;
use App\Enum\MessageType;
use App\Tests\AppWebTestCase;

final class AntiCheatSecurityTest extends AppWebTestCase
{
    public function testAdminRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/login');
    }

    public function testTargetPageNeverRevealsSantaName(): void
    {
        $this->ensureSettings();
        $alice = $this->createParticipant('Alice', 'alice-anti@ex.com');
        $bob = $this->createParticipant('Bob', 'bob-anti@ex.com');
        $cara = $this->createParticipant('Cara', 'cara-anti@ex.com');

        // Cara → Alice → Bob → Cara
        $this->em->persist((new Assignment())->setSanta($cara)->setTarget($alice));
        $this->em->persist((new Assignment())->setSanta($alice)->setTarget($bob));
        $this->em->persist((new Assignment())->setSanta($bob)->setTarget($cara));
        $bob->addWish((new Wish())->setTitle('Chaussettes')->setEstimatedPrice(12)->setPreferenceOrder(1)->setUrl('https://example.com/gift'));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$alice->getTokenSecret());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Tu offres à Bob');
        self::assertSelectorTextContains('body', 'Chaussettes');
        self::assertSelectorTextNotContains('body', 'Cara');
        self::assertSelectorTextNotContains('body', 'ton Santa est');
        self::assertSelectorTextNotContains('body', 'Santa (Cara');

        $link = $crawler->filter('a[href="https://example.com/gift"]');
        self::assertGreaterThan(0, $link->count());
        self::assertStringContainsString('noreferrer', (string) $link->attr('rel'));
    }

    public function testParticipantPageSendsReferrerPolicy(): void
    {
        $this->ensureSettings();
        $p = $this->createParticipant('RefPol', 'refpol@ex.com');
        $this->client->request('GET', '/participant/'.$p->getTokenSecret());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
    }

    public function testMessageMetaNeverExposesSantaName(): void
    {
        $this->ensureSettings();
        $a = $this->createParticipant('MsgA', 'msga@ex.com');
        $b = $this->createParticipant('MsgB', 'msgb@ex.com');
        $c = $this->createParticipant('MsgC', 'msgc@ex.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($c));
        $this->em->persist((new Assignment())->setSanta($c)->setTarget($a));

        $message = (new Message())
            ->setSanta($c)
            ->setTarget($a)
            ->setType(MessageType::ToTarget)
            ->setBody('Coucou anonyme');
        $this->em->persist($message);
        $this->em->flush();

        $this->client->request('GET', '/participant/'.$a->getTokenSecret().'/messages/santa');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.chat-bubble', 'Coucou anonyme');
        self::assertSelectorTextContains('.chat-bubble-meta', 'Ton Santa');
        self::assertSelectorTextNotContains('.chat', 'MsgC');
        self::assertSelectorTextNotContains('body', 'Santa (MsgC');
    }

    public function testDeleteWishOfAnotherParticipantReturns404(): void
    {
        $this->ensureSettings(50);
        $owner = $this->createParticipant('OwnerAnti', 'owner-anti@ex.com');
        $other = $this->createParticipant('OtherAnti', 'other-anti@ex.com');
        $wish = (new Wish())->setTitle('Secret')->setEstimatedPrice(10)->setPreferenceOrder(1);
        $owner->addWish($wish);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/participant/'.$other->getTokenSecret().'/wishes/'.$wish->getId().'/delete',
            ['_token' => 'whatever']
        );
        self::assertResponseStatusCodeSame(404);
        self::assertNotNull($this->em->find(Wish::class, $wish->getId()));
    }

    public function testCanAddWishAfterDraw(): void
    {
        $this->ensureSettings(50);
        $a = $this->createParticipant('LockA', 'locka-anti@ex.com');
        $b = $this->createParticipant('LockB', 'lockb-anti@ex.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$a->getTokenSecret());
        self::assertSelectorTextContains('body', 'Ajouter un souhait');
        $form = $crawler->selectButton('Ajouter un souhait')->form([
            'wish[title]' => 'Après tirage',
            'wish[estimatedPrice]' => '5',
            'wish[preferenceOrder]' => '1',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/participant/'.$a->getTokenSecret());
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertSelectorTextContains('table', 'Après tirage');
    }

    public function testMessagingWithoutAssignmentIsRejected(): void
    {
        $this->ensureSettings();
        $p = $this->createParticipant('NoAssign', 'noassign@ex.com');

        $this->client->request('GET', '/participant/'.$p->getTokenSecret());
        $content = $this->client->getResponse()->getContent() ?: '';
        self::assertStringNotContainsString('Écrire à ma cible', $content);
        self::assertStringNotContainsString('Écrire à mon Santa', $content);
        self::assertStringNotContainsString('/messages/target', $content);
        self::assertStringNotContainsString('/messages/santa', $content);

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/messages/target', [
            'message_form' => [
                'body' => 'Should fail',
                '_token' => 'invalid',
            ],
        ]);
        self::assertResponseRedirects('/participant/'.$p->getTokenSecret());
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/messages/santa', [
            'message_form' => [
                'body' => 'Should fail',
                '_token' => 'invalid',
            ],
        ]);
        self::assertResponseRedirects('/participant/'.$p->getTokenSecret());
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
    }

    public function testDrawRunRequiresConfirmation(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $this->createParticipant('D1', 'd1-anti@ex.com');
        $this->createParticipant('D2', 'd2-anti@ex.com');
        $this->createParticipant('D3', 'd3-anti@ex.com');

        $crawler = $this->client->request('GET', '/admin/draw');
        $form = $crawler->selectButton('Lancer le tirage')->form();
        // Soumettre sans la case confirm
        $values = $form->getPhpValues();
        unset($values['confirm']);
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects('/admin/draw');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-warning');
        self::assertSame(0, $this->em->getRepository(Assignment::class)->count([]));
    }
}
