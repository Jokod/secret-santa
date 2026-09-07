<?php

namespace App\Tests\Functional;

use App\Controller\Admin\SecurityController;
use App\Tests\AppWebTestCase;

final class SecurityControllerCoverageTest extends AppWebTestCase
{
    public function testLoginWhenAlreadyAuthenticatedRedirectsToDashboard(): void
    {
        $this->loginAdmin();
        $this->client->request('GET', '/admin/login');
        self::assertResponseRedirects('/admin');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Tableau de bord');
    }

    public function testLogoutRequiresPostWithCsrf(): void
    {
        $this->loginAdmin();

        // GET sans CSRF : pas de déconnexion
        $this->client->request('GET', '/admin/logout');
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', '/admin');
        $form = $crawler->filter('form.logout-form')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/');

        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/login');
    }

    public function testLogoutControllerMethodThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Logout intercepté par le firewall.');
        (new SecurityController())->logout();
    }
}
