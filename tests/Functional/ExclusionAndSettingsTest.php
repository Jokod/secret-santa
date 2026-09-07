<?php

namespace App\Tests\Functional;

use App\Entity\Exclusion;
use App\Tests\AppWebTestCase;

final class ExclusionAndSettingsTest extends AppWebTestCase
{
    public function testAdminCanCreateMutualExclusion(): void
    {
        $this->loginAdmin();
        $a = $this->createParticipant('Anne', 'anne@example.com');
        $b = $this->createParticipant('Ben', 'ben@example.com');

        $crawler = $this->client->request('GET', '/admin/exclusions');
        $form = $crawler->selectButton('Ajouter l’exclusion')->form([
            'exclusion[source]' => (string) $a->getId(),
            'exclusion[target]' => (string) $b->getId(),
            'exclusion[mutual]' => true,
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();

        $exclusions = $this->em->getRepository(Exclusion::class)->findAll();
        self::assertCount(2, $exclusions);
    }

    public function testSettingsCanBeSavedAndLockedAfterDraw(): void
    {
        $this->loginAdmin();
        $this->ensureSettings(40);

        $crawler = $this->client->request('GET', '/admin/settings');
        $form = $crawler->selectButton('Enregistrer')->form([
            'edition_settings[budgetMax]' => '75',
            'edition_settings[welcomeEmailTemplate]' => 'Hello {SANTA} budget {BUDGET}',
            'edition_settings[resultEmailTemplate]' => '{SANTA} -> {TARGET} ({BUDGET}) {LINK}',
            'edition_settings[reminderEmailTemplate]' => 'Reminder {SANTA} {LINK}',
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');

        $this->createParticipant('P1', 'p1@example.com');
        $this->createParticipant('P2', 'p2@example.com');
        $this->createParticipant('P3', 'p3@example.com');

        $crawler = $this->client->request('GET', '/admin/draw');
        $this->client->submit($crawler->selectButton('Lancer le tirage')->form([
            'confirm' => '1',
            'confirm_missing_wishes' => '1',
        ]));
        self::assertResponseRedirects('/admin/draw');

        $this->client->request('GET', '/admin/settings');
        self::assertSelectorExists('.locked-banner');
        self::assertSelectorNotExists('.actions-row button[type="submit"]');
    }
}
