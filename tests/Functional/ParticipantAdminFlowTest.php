<?php

namespace App\Tests\Functional;

use App\Entity\Assignment;
use App\Entity\Wish;
use App\Tests\AppWebTestCase;
use Symfony\Component\Mime\Email;

final class ParticipantAdminFlowTest extends AppWebTestCase
{
    public function testAdminCanCreateParticipantAndWelcomeEmailIsSent(): void
    {
        $this->ensureSettings();
        $this->loginAdmin();

        $this->client->request('GET', '/admin/participants/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Enregistrer', [
            'participant[name]' => 'Alice',
            'participant[email]' => 'alice@example.com',
        ]);

        self::assertResponseRedirects('/admin/participants');
        self::assertEmailCount(1);
        /** @var Email $email */
        $email = self::getMailerMessage();
        self::assertEmailHeaderSame($email, 'To', 'alice@example.com');
        self::assertEmailHtmlBodyContains($email, 'Secret Santa');

        $this->client->followRedirect();
        self::assertSelectorTextContains('table', 'Alice');
    }

    public function testInvalidParticipantTokenReturns404(): void
    {
        $this->client->request('GET', '/participant/'.str_repeat('a', 64));
        self::assertResponseStatusCodeSame(404);
    }

    public function testParticipantCanAddWishWithinBudget(): void
    {
        $this->ensureSettings(50);
        $participant = $this->createParticipant('Bob', 'bob@example.com');

        $crawler = $this->client->request('GET', '/participant/'.$participant->getTokenSecret());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ton espace');

        $form = $crawler->selectButton('Ajouter un souhait')->form([
            'wish[title]' => 'Livre',
            'wish[description]' => 'SF',
            'wish[url]' => 'https://example.com/livre',
            'wish[estimatedPrice]' => '25',
            'wish[preferenceOrder]' => '1',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('table', 'Livre');
    }

    public function testWishAboveBudgetIsAcceptedWithWarning(): void
    {
        $this->ensureSettings(50);
        $participant = $this->createParticipant('Carla', 'carla@example.com');

        $crawler = $this->client->request('GET', '/participant/'.$participant->getTokenSecret());
        $form = $crawler->selectButton('Ajouter un souhait')->form([
            'wish[title]' => 'Trop cher',
            'wish[estimatedPrice]' => '80',
            'wish[preferenceOrder]' => '1',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('table', 'Trop cher');
        self::assertSelectorExists('.wish-over-budget');
        self::assertSelectorExists('.flash-warning');
        self::assertSelectorTextContains('.flash-warning', 'dépasse le budget');
    }

    public function testParticipantNeverSeesOwnSantaName(): void
    {
        $this->ensureSettings();
        $alice = $this->createParticipant('Alice', 'a@example.com');
        $bob = $this->createParticipant('Bob', 'b@example.com');
        $cara = $this->createParticipant('Cara', 'c@example.com');

        // Alice -> Bob, Bob -> Cara, Cara -> Alice
        $this->em->persist((new Assignment())->setSanta($alice)->setTarget($bob));
        $this->em->persist((new Assignment())->setSanta($bob)->setTarget($cara));
        $this->em->persist((new Assignment())->setSanta($cara)->setTarget($alice));
        $bob->addWish((new Wish())->setTitle('Chaussettes')->setEstimatedPrice(10)->setPreferenceOrder(1));
        $this->em->flush();

        $this->client->request('GET', '/participant/'.$alice->getTokenSecret());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Tu offres à Bob');
        self::assertSelectorTextContains('table', 'Chaussettes');
        // Page must not reveal that Cara is Alice's Santa
        self::assertSelectorTextNotContains('body', 'Cara');
        self::assertSelectorTextNotContains('body', 'ton Santa est');
    }

    public function testCannotDeleteAssignedParticipant(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('A', 'a2@example.com');
        $b = $this->createParticipant('B', 'b2@example.com');
        $c = $this->createParticipant('C', 'c2@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($c));
        $this->em->persist((new Assignment())->setSanta($c)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/participants');
        self::assertResponseIsSuccessful();
        // Assigned participants: no delete button when locked
        self::assertSelectorNotExists('form[action$="/delete"] button');
        self::assertTrue($a->isAssigned());
    }
}
