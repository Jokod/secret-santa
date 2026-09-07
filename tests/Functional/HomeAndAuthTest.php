<?php

namespace App\Tests\Functional;

use App\Tests\AppWebTestCase;

final class HomeAndAuthTest extends AppWebTestCase
{
    public function testLandingPageShowsBrand(): void
    {
        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.brand-hero-logo');
        self::assertSame('Secret Santa Familial', $crawler->filter('.brand-hero-logo')->attr('alt'));
        self::assertSelectorExists('link[rel="icon"][href*="favicon.ico"]');
        self::assertSelectorExists('link[rel="icon"][href*="logo-mark.png"]');
        self::assertSelectorExists('.site-header .brand-logo');
    }

    public function testAdminRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/login');
    }

    public function testAdminCanLoginAndSeeDashboard(): void
    {
        $this->createAdmin();
        $this->client->request('GET', '/admin/login');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Se connecter', [
            '_username' => 'admin@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/admin');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Tableau de bord');
    }

    public function testInvalidLoginShowsError(): void
    {
        $this->createAdmin();
        $this->client->request('GET', '/admin/login');
        $this->client->submitForm('Se connecter', [
            '_username' => 'admin@example.com',
            '_password' => 'wrong',
        ]);
        self::assertResponseRedirects('/admin/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-danger');
    }
}
