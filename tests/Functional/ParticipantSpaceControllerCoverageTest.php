<?php

namespace App\Tests\Functional;

use App\Entity\Assignment;
use App\Entity\Message;
use App\Entity\Wish;
use App\Enum\MessageType;
use App\Tests\AppWebTestCase;
use Symfony\Component\HttpFoundation\Response;

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
        self::assertSelectorTextContains('.wish-card', 'Toujours ok');
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
        self::assertSelectorNotExists('.wish-card');
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
        self::assertSelectorTextContains('.wish-card', 'Nouveau titre');

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

    public function testEditWishAboveBudgetIsAccepted(): void
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
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.wish-card', 'Trop cher');
        self::assertSelectorExists('.wish-over-budget');
        self::assertSelectorExists('.flash-warning');

        $this->em->clear();
        $updated = $this->em->find(Wish::class, $wish->getId());
        self::assertNotNull($updated);
        self::assertSame('Trop cher', $updated->getTitle());
        self::assertSame(80.0, $updated->getEstimatedPrice());
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
        self::assertSelectorTextContains('.wish-card', 'Après tirage');
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
        $this->client->submit($crawler->filter(sprintf('form[action$="/wishes/%d/delete"]', $wish->getId()))->form());
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
        $this->client->submit($crawler->filter(sprintf('form[action$="/wishes/%d/delete"]', $wish->getId()))->form());
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

    public function testSendMessageToSantaJsonReturnsPayloadWithoutRedirect(): void
    {
        $this->ensureSettings();
        $a = $this->createParticipant('JsonSantaA', 'jsonsantaa@example.com');
        $b = $this->createParticipant('JsonSantaB', 'jsonsantab@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$b->getTokenSecret().'/messages/santa');
        $token = $crawler->filter('form input[name="message_form[_token]"]')->attr('value');

        $this->client->request(
            'POST',
            '/participant/'.$b->getTokenSecret().'/messages/santa',
            [
                'message_form' => [
                    'body' => 'Ping JSON live',
                    '_token' => $token,
                ],
            ],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame('Ping JSON live', $payload['message']['body'] ?? null);
        self::assertSame(MessageType::ToSanta->value, $payload['message']['type'] ?? null);
        self::assertArrayHasKey('id', $payload['message']);
        self::assertArrayHasKey('createdAt', $payload['message']);
    }

    public function testSendMessageToTargetJsonReturnsPayloadWithoutRedirect(): void
    {
        $this->ensureSettings();
        $a = $this->createParticipant('JsonTargetA', 'jsontargeta@example.com');
        $b = $this->createParticipant('JsonTargetB', 'jsontargetb@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$a->getTokenSecret().'/messages/target');
        $token = $crawler->filter('form input[name="message_form[_token]"]')->attr('value');

        $this->client->request(
            'POST',
            '/participant/'.$a->getTokenSecret().'/messages/target',
            [
                'message_form' => [
                    'body' => 'Ping cible JSON',
                    '_token' => $token,
                ],
            ],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame('Ping cible JSON', $payload['message']['body'] ?? null);
        self::assertSame(MessageType::ToTarget->value, $payload['message']['type'] ?? null);
    }

    public function testInvalidJsonMessageToTargetReturns422(): void
    {
        $this->ensureSettings();
        $a = $this->createParticipant('JsonInvTargetA', 'jsoninvtargeta@example.com');
        $b = $this->createParticipant('JsonInvTargetB', 'jsoninvtargetb@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$a->getTokenSecret().'/messages/target');
        $token = $crawler->filter('form input[name="message_form[_token]"]')->attr('value');

        $this->client->request(
            'POST',
            '/participant/'.$a->getTokenSecret().'/messages/target',
            [
                'message_form' => [
                    'body' => '',
                    '_token' => $token,
                ],
            ],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('{"error":"Message invalide."}', $this->client->getResponse()->getContent());
    }

    public function testInvalidJsonMessageToSantaReturns422(): void
    {
        $this->ensureSettings();
        $a = $this->createParticipant('JsonInvSantaA', 'jsoninvsantaa@example.com');
        $b = $this->createParticipant('JsonInvSantaB', 'jsoninvsantab@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$b->getTokenSecret().'/messages/santa');
        $token = $crawler->filter('form input[name="message_form[_token]"]')->attr('value');

        $this->client->request(
            'POST',
            '/participant/'.$b->getTokenSecret().'/messages/santa',
            [
                'message_form' => [
                    'body' => '',
                    '_token' => $token,
                ],
            ],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('{"error":"Message invalide."}', $this->client->getResponse()->getContent());
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

    public function testElfGameStartDisabled(): void
    {
        $settings = $this->ensureSettings();
        $settings->setEasterEggEnabled(false);
        $this->em->flush();
        $p = $this->createParticipant('ElfOff', 'elfoff@example.com');

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-game/start', [], [], [
            'HTTP_X_CSRF_TOKEN' => 'anything',
        ]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('{"ok":false,"error":"disabled"}', $this->client->getResponse()->getContent());
    }

    public function testElfGameStartAndScoreCsrf(): void
    {
        $settings = $this->ensureSettings();
        $settings->setEasterEggEnabled(true);
        $this->em->flush();
        $p = $this->createParticipant('ElfCsrf', 'elfcsrf@example.com');

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-game/start', [], [], [
            'HTTP_X_CSRF_TOKEN' => 'bad-token',
        ]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-score', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'bad-token',
        ], json_encode(['score' => 10, 'sessionToken' => 'x'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(403);
        self::assertSame('{"ok":false,"error":"csrf"}', $this->client->getResponse()->getContent());
    }

    public function testElfGameAntiCheatAndScoreboard(): void
    {
        $settings = $this->ensureSettings();
        $settings->setEasterEggEnabled(true);
        $this->em->flush();
        $p = $this->createParticipant('ElfOk', 'elfok@example.com');

        $crawler = $this->client->request('GET', '/participant/'.$p->getTokenSecret());
        $csrf = $crawler->filter('[data-elf-game-csrf-token-value]')->attr('data-elf-game-csrf-token-value');
        self::assertNotEmpty($csrf);

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-score', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ], json_encode(['score' => 42], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-game/start', [], [], [
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ]);
        self::assertResponseIsSuccessful();
        $start = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($start['ok']);
        $sessionToken = $start['sessionToken'];
        self::assertNotEmpty($sessionToken);

        $this->em->clear();
        $p = $this->em->getRepository(\App\Entity\Participant::class)->findOneBy(['email' => 'elfok@example.com']);
        self::assertNotNull($p);
        $p->setElfGameStartedAt(new \DateTimeImmutable('-60 seconds'));
        $this->em->flush();

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-score', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ], json_encode(['score' => 0, 'sessionToken' => $sessionToken], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-game/start', [], [], [
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ]);
        $sessionToken = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['sessionToken'];
        $this->em->clear();
        $p = $this->em->getRepository(\App\Entity\Participant::class)->findOneBy(['email' => 'elfok@example.com']);
        self::assertNotNull($p);
        $p->setElfGameStartedAt(new \DateTimeImmutable('-10 seconds'));
        $this->em->flush();

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-score', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ], json_encode(['score' => 42, 'sessionToken' => $sessionToken], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(425);

        $this->em->clear();
        $p = $this->em->getRepository(\App\Entity\Participant::class)->findOneBy(['email' => 'elfok@example.com']);
        self::assertNotNull($p);
        $p->setElfGameStartedAt(new \DateTimeImmutable('-60 seconds'));
        $this->em->flush();

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-score', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ], json_encode(['score' => 42, 'sessionToken' => $sessionToken], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertTrue($payload['updated']);
        self::assertSame(42, $payload['bestScore']);

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-score', [
            '_token' => $csrf,
            'score' => '12',
            'sessionToken' => $sessionToken,
        ]);
        self::assertResponseStatusCodeSame(409);

        $this->loginAdmin();
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.scoreboard');
        self::assertSelectorTextContains('.scoreboard-name', 'Elfok');
        self::assertSelectorTextContains('.scoreboard-score', '42');
    }

    public function testElfGameScoreDisabledAndExpiredSession(): void
    {
        $settings = $this->ensureSettings();
        $settings->setEasterEggEnabled(false);
        $this->em->flush();
        $p = $this->createParticipant('ElfScoreOff', 'elfscoreoff@example.com');

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-score', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'x',
        ], json_encode(['score' => 10, 'sessionToken' => 'x'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(403);

        $settings->setEasterEggEnabled(true);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$p->getTokenSecret());
        $csrf = $crawler->filter('[data-elf-game-csrf-token-value]')->attr('data-elf-game-csrf-token-value');

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-game/start', [], [], [
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ]);
        $sessionToken = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['sessionToken'];

        $this->em->clear();
        $p = $this->em->getRepository(\App\Entity\Participant::class)->findOneBy(['email' => 'elfscoreoff@example.com']);
        self::assertNotNull($p);
        $p->setElfGameStartedAt(new \DateTimeImmutable('-120 seconds'));
        $this->em->flush();

        $this->client->request('POST', '/participant/'.$p->getTokenSecret().'/elf-score', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ], json_encode(['score' => 10, 'sessionToken' => $sessionToken], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('expired', (string) $this->client->getResponse()->getContent());
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

    public function testMessagesTargetExposesMercureSubscribeUrl(): void
    {
        $this->ensureSettings();
        $santa = $this->createParticipant('Merc Cov Santa', 'merccovsanta@example.com');
        $target = $this->createParticipant('Merc Cov Target', 'merccovtarget@example.com');
        $this->em->persist((new Assignment())->setSanta($santa)->setTarget($target));
        $this->em->persist((new Assignment())->setSanta($target)->setTarget($santa));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$santa->getTokenSecret().'/messages/target');
        self::assertResponseIsSuccessful();
        $node = $crawler->filter('[data-controller="chat-live"]');
        self::assertGreaterThan(0, $node->count());
        $subscribeUrl = (string) $node->attr('data-chat-live-subscribe-url-value');
        self::assertStringContainsString('topic=', $subscribeUrl);
        self::assertStringContainsString('urn%3Asanta%3Athread%3A', $subscribeUrl);
        self::assertStringNotContainsString('//.well-known', $subscribeUrl);
        self::assertStringNotContainsString('authorization=', $subscribeUrl);
        self::assertNotNull($this->client->getCookieJar()->get('mercureAuthorization', '/.well-known/mercure'));
        self::assertNotEmpty($node->attr('data-chat-live-mark-read-url-template-value'));
        self::assertNotEmpty($node->attr('data-chat-live-mark-read-csrf-value'));
        self::assertSame('to_target', $node->attr('data-chat-live-mine-type-value'));
        self::assertSame('Toi (Santa)', $node->attr('data-chat-live-mine-label-value'));
        self::assertSame('Merc Cov Target', $node->attr('data-chat-live-theirs-label-value'));
    }

    public function testMarkMessageReadAsRecipient(): void
    {
        $this->ensureSettings();
        $santa = $this->createParticipant('Read Santa', 'readsanta@example.com');
        $target = $this->createParticipant('Read Target', 'readtarget@example.com');
        $this->em->persist((new Assignment())->setSanta($santa)->setTarget($target));
        $this->em->persist((new Assignment())->setSanta($target)->setTarget($santa));
        $message = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('Lu en live')
            ->setIsRead(false);
        $this->em->persist($message);
        $this->em->flush();
        $messageId = $message->getId();

        $crawler = $this->client->request('GET', '/participant/'.$target->getTokenSecret().'/messages/santa');
        self::assertResponseIsSuccessful();
        // Page GET already marked thread as read — reset for the POST endpoint.
        $this->em->clear();
        $stored = $this->em->find(Message::class, $messageId);
        self::assertNotNull($stored);
        $stored->setIsRead(false);
        $this->em->flush();

        $csrf = $crawler->filter('[data-chat-live-mark-read-csrf-value]')->attr('data-chat-live-mark-read-csrf-value');
        self::assertNotEmpty($csrf);

        $this->client->request(
            'POST',
            '/participant/'.$target->getTokenSecret().'/messages/'.$messageId.'/read',
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrf,
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ],
        );
        self::assertResponseIsSuccessful();
        self::assertSame('{"ok":true}', $this->client->getResponse()->getContent());
        $this->em->clear();
        self::assertTrue($this->em->find(Message::class, $messageId)?->isRead());
    }

    public function testMarkMessageReadRejectsWrongRecipient(): void
    {
        $this->ensureSettings();
        $santa = $this->createParticipant('Wrong Read Santa', 'wrongreadsanta@example.com');
        $target = $this->createParticipant('Wrong Read Target', 'wrongreadtarget@example.com');
        $this->em->persist((new Assignment())->setSanta($santa)->setTarget($target));
        $this->em->persist((new Assignment())->setSanta($target)->setTarget($santa));
        $message = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('Pas pour le santa')
            ->setIsRead(false);
        $this->em->persist($message);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/participant/'.$santa->getTokenSecret().'/messages/target');
        $csrf = $crawler->filter('[data-chat-live-mark-read-csrf-value]')->attr('data-chat-live-mark-read-csrf-value');

        $this->client->request(
            'POST',
            '/participant/'.$santa->getTokenSecret().'/messages/'.$message->getId().'/read',
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrf,
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('{"ok":false,"error":"forbidden"}', $this->client->getResponse()->getContent());
    }

    public function testMarkMessageReadRejectsInvalidCsrf(): void
    {
        $this->ensureSettings();
        $santa = $this->createParticipant('Csrf Read Santa', 'csrfreadsanta@example.com');
        $target = $this->createParticipant('Csrf Read Target', 'csrfreadtarget@example.com');
        $this->em->persist((new Assignment())->setSanta($santa)->setTarget($target));
        $this->em->persist((new Assignment())->setSanta($target)->setTarget($santa));
        $message = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('csrf')
            ->setIsRead(false);
        $this->em->persist($message);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/participant/'.$target->getTokenSecret().'/messages/'.$message->getId().'/read',
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => 'invalid',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('{"ok":false,"error":"csrf"}', $this->client->getResponse()->getContent());
    }
}
