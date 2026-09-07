<?php

namespace App\Tests\Functional;

use App\Entity\Assignment;
use App\Tests\AppWebTestCase;

final class DrawControllerCoverageTest extends AppWebTestCase
{
    public function testRunWithInvalidCsrf(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $this->createParticipant('A', 'draw-csrf-a@ex.com');
        $this->createParticipant('B', 'draw-csrf-b@ex.com');
        $this->createParticipant('C', 'draw-csrf-c@ex.com');

        $this->client->request('POST', '/admin/draw/run', ['_token' => 'invalid']);
        self::assertResponseRedirects('/admin/draw');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSelectorTextContains('.flash-danger', 'Jeton de sécurité invalide');
    }

    public function testRunWhenAlreadyActive(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $a = $this->createParticipant('A', 'draw-active-a@ex.com');
        $b = $this->createParticipant('B', 'draw-active-b@ex.com');
        $c = $this->createParticipant('C', 'draw-active-c@ex.com');

        // Extraire le jeton pendant que le formulaire "run" est encore affiché
        $crawler = $this->client->request('GET', '/admin/draw');
        $token = $crawler->filter('form[action$="/run"] input[name="_token"]')->attr('value');

        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($c));
        $this->em->persist((new Assignment())->setSanta($c)->setTarget($a));
        $this->em->flush();

        $this->client->request('POST', '/admin/draw/run', [
            '_token' => $token,
            'confirm' => '1',
            'confirm_missing_wishes' => '1',
        ]);
        self::assertResponseRedirects('/admin/draw');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSelectorTextContains('.flash-danger', 'déjà actif');
    }

    public function testRunWithoutConfirm(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $this->createParticipant('A', 'draw-noconfirm-run-a@ex.com');
        $this->createParticipant('B', 'draw-noconfirm-run-b@ex.com');
        $this->createParticipant('C', 'draw-noconfirm-run-c@ex.com');

        $crawler = $this->client->request('GET', '/admin/draw');
        $token = $crawler->filter('form[action$="/run"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/draw/run', ['_token' => $token]);
        self::assertResponseRedirects('/admin/draw');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-warning');
        self::assertSelectorTextContains('.flash-warning', 'confirmation');
    }

    public function testRunWithoutMissingWishesConfirm(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $this->createParticipant('A', 'draw-missing-a@ex.com');
        $this->createParticipant('B', 'draw-missing-b@ex.com');
        $this->createParticipant('C', 'draw-missing-c@ex.com');

        $crawler = $this->client->request('GET', '/admin/draw');
        self::assertSelectorExists('.hint-box.warning');
        self::assertSelectorTextContains('.hint-box.warning', 'sans souhaits');
        $token = $crawler->filter('form[action$="/run"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/draw/run', [
            '_token' => $token,
            'confirm' => '1',
        ]);
        self::assertResponseRedirects('/admin/draw');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-warning');
        self::assertSelectorTextContains('.flash-warning', 'confirmation dédiée');
        self::assertSame(0, $this->em->getRepository(Assignment::class)->count([]));
    }

    public function testResetWithoutConfirm(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $a = $this->createParticipant('A', 'draw-noconfirm-a@ex.com');
        $b = $this->createParticipant('B', 'draw-noconfirm-b@ex.com');
        $c = $this->createParticipant('C', 'draw-noconfirm-c@ex.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($c));
        $this->em->persist((new Assignment())->setSanta($c)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/draw');
        $form = $crawler->selectButton('Réinitialiser')->form();
        $form->remove('confirm');
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/draw');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-warning');
        self::assertSelectorTextContains('.flash-warning', 'confirmation');
        self::assertSame(3, $this->em->getRepository(Assignment::class)->count([]));
    }

    public function testResetWithInvalidCsrf(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $a = $this->createParticipant('A', 'draw-reset-csrf-a@ex.com');
        $b = $this->createParticipant('B', 'draw-reset-csrf-b@ex.com');
        $c = $this->createParticipant('C', 'draw-reset-csrf-c@ex.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($c));
        $this->em->persist((new Assignment())->setSanta($c)->setTarget($a));
        $this->em->flush();

        $this->client->request('POST', '/admin/draw/reset', [
            '_token' => 'invalid',
            'confirm' => '1',
        ]);
        self::assertResponseRedirects('/admin/draw');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSelectorTextContains('.flash-danger', 'Jeton de sécurité invalide');
        self::assertSame(3, $this->em->getRepository(Assignment::class)->count([]));
    }

    public function testRemindSuccess(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $this->createParticipant('A', 'draw-remind-a@ex.com');
        $this->createParticipant('B', 'draw-remind-b@ex.com');
        $this->createParticipant('C', 'draw-remind-c@ex.com');

        $crawler = $this->client->request('GET', '/admin/draw');
        $this->client->submit($crawler->selectButton('Relancer uniquement ceux sans souhaits')->form());
        self::assertResponseRedirects('/admin/draw');
        self::assertEmailCount(3);
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertSelectorTextContains('.flash-success', 'Rappel envoyé');
    }

    public function testRemindWhenEveryoneHasWishes(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $a = $this->createParticipant('A', 'draw-remind-full-a@ex.com');
        $b = $this->createParticipant('B', 'draw-remind-full-b@ex.com');
        $c = $this->createParticipant('C', 'draw-remind-full-c@ex.com');
        foreach ([$a, $b, $c] as $participant) {
            $participant->addWish((new \App\Entity\Wish())->setTitle('Idee')->setEstimatedPrice(10)->setPreferenceOrder(1));
        }
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/draw');
        self::assertSelectorTextContains('.hint-box', 'Tous les participants ont au moins un souhait');

        $this->client->submit($crawler->filter('form[action$="/remind"]')->form());
        self::assertResponseRedirects('/admin/draw');
        self::assertEmailCount(0);
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertSelectorTextContains('.flash-success', 'Tous les participants ont déjà au moins un souhait');
    }

    public function testRemindWithInvalidCsrf(): void
    {
        $this->loginAdmin();
        $this->ensureSettings();
        $this->createParticipant('A', 'draw-remind-csrf-a@ex.com');

        $this->client->request('POST', '/admin/draw/remind', ['_token' => 'invalid']);
        self::assertResponseRedirects('/admin/draw');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSelectorTextContains('.flash-danger', 'Jeton de sécurité invalide');
        self::assertEmailCount(0);
    }
}
