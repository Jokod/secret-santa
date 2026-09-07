<?php

namespace App\Tests\Functional;

use App\Entity\Assignment;
use App\Entity\Participant;
use App\Tests\AppWebTestCase;
use Symfony\Component\Mime\Email;

final class ParticipantControllerCoverageTest extends AppWebTestCase
{
    public function testEditParticipant(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $p = $this->createParticipant('Editme', 'editme@example.com');

        $crawler = $this->client->request('GET', '/admin/participants/'.$p->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Modifier Editme');

        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'participant[name]' => '  pierre-alain  ',
            'participant[email]' => 'edited@example.com',
        ]));
        self::assertResponseRedirects('/admin/participants');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertSelectorTextContains('table', 'Pierre-Alain');

        $this->em->clear();
        $updated = $this->em->getRepository(Participant::class)->find($p->getId());
        self::assertSame('Pierre-Alain', $updated?->getName());
        self::assertSame('edited@example.com', $updated?->getEmail());
    }

    public function testDeleteParticipant(): void
    {
        $this->loginAdmin();
        $p = $this->createParticipant('DeleteMe', 'deleteme@example.com');

        $crawler = $this->client->request('GET', '/admin/participants');
        $this->client->submit($crawler->selectButton('Supprimer')->form());
        self::assertResponseRedirects('/admin/participants');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertNull($this->em->getRepository(Participant::class)->find($p->getId()));
    }

    public function testDeleteWithInvalidCsrf(): void
    {
        $this->loginAdmin();
        $p = $this->createParticipant('CsrfDel', 'csrfdel@example.com');

        $this->client->request('POST', '/admin/participants/'.$p->getId().'/delete', ['_token' => 'invalid']);
        self::assertResponseRedirects('/admin/participants');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertNotNull($this->em->getRepository(Participant::class)->find($p->getId()));
    }

    public function testDeleteAssignedBlocked(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('AsgA', 'asga@example.com');
        $b = $this->createParticipant('AsgB', 'asgb@example.com');
        $c = $this->createParticipant('AsgC', 'asgc@example.com');

        $crawler = $this->client->request('GET', '/admin/participants');
        $token = $crawler->filter(sprintf('form[action$="/participants/%d/delete"] input[name="_token"]', $a->getId()))->attr('value');

        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($c));
        $this->em->persist((new Assignment())->setSanta($c)->setTarget($a));
        $this->em->flush();

        $this->client->request('POST', '/admin/participants/'.$a->getId().'/delete', ['_token' => $token]);
        self::assertResponseRedirects('/admin/participants');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSelectorTextContains('.flash-danger', 'déjà assigné');
    }

    public function testDeleteUnassignedWhenLocked(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('LockA', 'locka-p@example.com');
        $b = $this->createParticipant('LockB', 'lockb-p@example.com');
        $orphan = $this->createParticipant('Orphan', 'orphan-p@example.com');

        $crawler = $this->client->request('GET', '/admin/participants');
        $token = $crawler->filter(sprintf('form[action$="/participants/%d/delete"] input[name="_token"]', $orphan->getId()))->attr('value');

        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $this->client->request('POST', '/admin/participants/'.$orphan->getId().'/delete', ['_token' => $token]);
        self::assertResponseRedirects('/admin/participants');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSelectorTextContains('.flash-danger', 'verrouillés');
        self::assertNotNull($this->em->getRepository(Participant::class)->find($orphan->getId()));
    }

    public function testNewWhenLockedRedirects(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('NewLockA', 'newlocka@example.com');
        $b = $this->createParticipant('NewLockB', 'newlockb@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $this->client->request('GET', '/admin/participants/new');
        self::assertResponseRedirects('/admin/participants');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
    }

    public function testEditWhenLockedRedirects(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('EditLockA', 'editlocka@example.com');
        $b = $this->createParticipant('EditLockB', 'editlockb@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $this->client->request('GET', '/admin/participants/'.$a->getId().'/edit');
        self::assertResponseRedirects('/admin/participants');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
    }

    public function testResendWelcome(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $p = $this->createParticipant('Resend', 'resend@example.com');

        $crawler = $this->client->request('GET', '/admin/participants');
        $this->client->submit($crawler->selectButton('Renvoyer l’email')->form());
        self::assertResponseRedirects('/admin/participants');
        self::assertEmailCount(1);
        /** @var Email $email */
        $email = self::getMailerMessage();
        self::assertEmailHeaderSame($email, 'To', 'resend@example.com');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
    }

    public function testResendWelcomeInvalidCsrf(): void
    {
        $this->loginAdmin();
        $p = $this->createParticipant('ResendCsrf', 'resendcsrf@example.com');

        $this->client->request('POST', '/admin/participants/'.$p->getId().'/resend-welcome', ['_token' => 'bad']);
        self::assertResponseRedirects('/admin/participants');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertEmailCount(0);
    }

    public function testNewWithInvalidForm(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();

        $crawler = $this->client->request('GET', '/admin/participants/new');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'participant[name]' => 'BadEmail',
            'participant[email]' => 'not-an-email',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form');
        self::assertNull($this->em->getRepository(Participant::class)->findOneBy(['name' => 'BadEmail']));
    }
}
