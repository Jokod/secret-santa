<?php

namespace App\Tests\Functional;

use App\Entity\Assignment;
use App\Entity\EditionSettings;
use App\Tests\AppWebTestCase;

final class SettingsControllerCoverageTest extends AppWebTestCase
{
    public function testSaveSuccess(): void
    {
        $this->loginAdmin();
        $this->ensureSettings(40);

        $crawler = $this->client->request('GET', '/admin/settings');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'edition_settings[budgetMax]' => '99.5',
            'edition_settings[welcomeEmailTemplate]' => 'W {SANTA} {LINK}',
            'edition_settings[resultEmailTemplate]' => 'R {SANTA} {TARGET}',
            'edition_settings[reminderEmailTemplate]' => 'Rem {SANTA}',
        ]));
        self::assertResponseRedirects('/admin/settings');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-success');

        $this->em->clear();
        $settings = $this->em->find(EditionSettings::class, EditionSettings::SINGLETON_ID);
        self::assertNotNull($settings);
        self::assertSame(99.5, $settings->getBudgetMax());
    }

    public function testInvalidForm(): void
    {
        $this->loginAdmin();
        $this->ensureSettings(50);

        $crawler = $this->client->request('GET', '/admin/settings');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'edition_settings[budgetMax]' => '0',
            'edition_settings[welcomeEmailTemplate]' => 'W',
            'edition_settings[resultEmailTemplate]' => 'R',
            'edition_settings[reminderEmailTemplate]' => 'Rem',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form');
        self::assertSelectorExists('.form-errors');
        self::assertSelectorNotExists('.flash-success');
        $this->em->clear();
        $settings = $this->em->find(EditionSettings::class, EditionSettings::SINGLETON_ID);
        self::assertSame(50.0, $settings?->getBudgetMax());
    }

    public function testSaveWhenLocked(): void
    {
        $this->loginAdmin();
        $this->ensureSettings(50);
        $a = $this->createParticipant('SetA', 'seta@example.com');
        $b = $this->createParticipant('SetB', 'setb@example.com');
        $this->em->persist((new Assignment())->setSanta($a)->setTarget($b));
        $this->em->persist((new Assignment())->setSanta($b)->setTarget($a));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/settings');
        self::assertSelectorExists('.locked-banner');
        $token = $crawler->filter('input[name="edition_settings[_token]"]')->attr('value');

        $this->client->request('POST', '/admin/settings', [
            'edition_settings' => [
                'budgetMax' => '10',
                'welcomeEmailTemplate' => 'hack',
                'resultEmailTemplate' => 'hack',
                'reminderEmailTemplate' => 'hack',
                '_token' => $token,
            ],
        ]);
        self::assertResponseRedirects('/admin/settings');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
        self::assertSelectorTextContains('.flash-danger', 'verrouillés');

        $this->em->clear();
        $settings = $this->em->find(EditionSettings::class, EditionSettings::SINGLETON_ID);
        self::assertSame(50.0, $settings?->getBudgetMax());
    }
}
