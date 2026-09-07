<?php

namespace App\Tests\Functional;

use App\Entity\Assignment;
use App\Entity\Exclusion;
use App\Tests\AppWebTestCase;

final class ExclusionControllerCoverageTest extends AppWebTestCase
{
    public function testCreateNonMutualExclusion(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('ExA', 'exa@example.com');
        $b = $this->createParticipant('ExB', 'exb@example.com');

        $crawler = $this->client->request('GET', '/admin/exclusions');
        $form = $crawler->selectButton('Ajouter l’exclusion')->form([
            'exclusion[source]' => (string) $a->getId(),
            'exclusion[target]' => (string) $b->getId(),
            'exclusion[mutual]' => false,
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/exclusions');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');

        $exclusions = $this->em->getRepository(Exclusion::class)->findAll();
        self::assertCount(1, $exclusions);
    }

    public function testSelfExclusionRejected(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('SelfEx', 'selfex@example.com');
        $this->createParticipant('OtherEx', 'otherex@example.com');

        $crawler = $this->client->request('GET', '/admin/exclusions');
        $form = $crawler->selectButton('Ajouter l’exclusion')->form([
            'exclusion[source]' => (string) $a->getId(),
            'exclusion[target]' => (string) $a->getId(),
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/exclusions');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSelectorTextContains('.flash-danger', 'elle-même');
        self::assertSame(0, $this->em->getRepository(Exclusion::class)->count([]));
    }

    public function testDuplicateExclusionWarned(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('DupA', 'dupa@example.com');
        $b = $this->createParticipant('DupB', 'dupb@example.com');
        $this->em->persist((new Exclusion())->setSource($a)->setTarget($b));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/exclusions');
        $form = $crawler->selectButton('Ajouter l’exclusion')->form([
            'exclusion[source]' => (string) $a->getId(),
            'exclusion[target]' => (string) $b->getId(),
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/exclusions');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-warning');
        self::assertSelectorTextContains('.flash-warning', 'existe déjà');
        self::assertSame(1, $this->em->getRepository(Exclusion::class)->count([]));
    }

    public function testMutualWhenReverseAlreadyExists(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('MutA', 'muta@example.com');
        $b = $this->createParticipant('MutB', 'mutb@example.com');
        $this->em->persist((new Exclusion())->setSource($a)->setTarget($b));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/exclusions');
        $form = $crawler->selectButton('Ajouter l’exclusion')->form([
            'exclusion[source]' => (string) $b->getId(),
            'exclusion[target]' => (string) $a->getId(),
            'exclusion[mutual]' => true,
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertSame(2, $this->em->getRepository(Exclusion::class)->count([]));
    }

    public function testCreateWhenLocked(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('LockExA', 'lockexa@example.com');
        $b = $this->createParticipant('LockExB', 'lockexb@example.com');
        $c = $this->createParticipant('LockExC', 'lockexc@example.com');

        $crawler = $this->client->request('GET', '/admin/exclusions');
        $token = $crawler->filter('input[name="exclusion[_token]"]')->attr('value');

        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($c));
        $this->em->persist((new Assignment())->setSanta($c)->setTarget($a));
        $this->em->flush();

        $this->client->request('POST', '/admin/exclusions', [
            'exclusion' => [
                'source' => (string) $a->getId(),
                'target' => (string) $b->getId(),
                '_token' => $token,
            ],
        ]);
        self::assertResponseRedirects('/admin/exclusions');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSelectorTextContains('.flash-danger', 'verrouillés');
    }

    public function testDeleteExclusion(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('DelExA', 'delexa@example.com');
        $b = $this->createParticipant('DelExB', 'delexb@example.com');
        $exclusion = (new Exclusion())->setSource($a)->setTarget($b);
        $this->em->persist($exclusion);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/exclusions');
        $this->client->submit($crawler->selectButton('Supprimer')->form());
        self::assertResponseRedirects('/admin/exclusions');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertSame(0, $this->em->getRepository(Exclusion::class)->count([]));
    }

    public function testDeleteWithInvalidCsrf(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('CsrfExA', 'csrfexa@example.com');
        $b = $this->createParticipant('CsrfExB', 'csrfexb@example.com');
        $exclusion = (new Exclusion())->setSource($a)->setTarget($b);
        $this->em->persist($exclusion);
        $this->em->flush();
        $id = $exclusion->getId();

        $this->client->request('POST', '/admin/exclusions/'.$id.'/delete', ['_token' => 'invalid']);
        self::assertResponseRedirects('/admin/exclusions');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSame(1, $this->em->getRepository(Exclusion::class)->count([]));
    }

    public function testDeleteWhenLocked(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('LockDelA', 'lockdela@example.com');
        $b = $this->createParticipant('LockDelB', 'lockdelb@example.com');
        $c = $this->createParticipant('LockDelC', 'lockdelc@example.com');
        $exclusion = (new Exclusion())->setSource($a)->setTarget($b);
        $this->em->persist($exclusion);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/exclusions');
        $token = $crawler->filter(sprintf('form[action$="/exclusions/%d/delete"] input[name="_token"]', $exclusion->getId()))->attr('value');

        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($c));
        $this->em->persist((new Assignment())->setSanta($c)->setTarget($a));
        $this->em->flush();

        $this->client->request('POST', '/admin/exclusions/'.$exclusion->getId().'/delete', ['_token' => $token]);
        self::assertResponseRedirects('/admin/exclusions');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSame(1, $this->em->getRepository(Exclusion::class)->count([]));
    }
}
