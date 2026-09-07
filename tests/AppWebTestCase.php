<?php

namespace App\Tests;

use App\Entity\EditionSettings;
use App\Entity\Participant;
use App\Entity\User;
use App\Service\ParticipantTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AppWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createAdmin(string $email = 'admin@example.com', string $password = 'password'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = (new User())->setEmail($email)->setRoles(['ROLE_ADMIN']);
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function loginAdmin(string $email = 'admin@example.com', string $password = 'password'): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]) ?? $this->createAdmin($email, $password);
        $this->client->loginUser($user);
    }

    protected function createParticipant(string $name, string $email): Participant
    {
        $token = static::getContainer()->get(ParticipantTokenGenerator::class)->generate();
        $participant = (new Participant())
            ->setName($name)
            ->setEmail($email)
            ->setTokenSecret($token);
        $this->em->persist($participant);
        $this->em->flush();

        return $participant;
    }

    protected function ensureSettings(float $budget = 50.0): EditionSettings
    {
        $repo = $this->em->getRepository(EditionSettings::class);
        $settings = $repo->find(EditionSettings::SINGLETON_ID) ?? new EditionSettings();
        $settings->setBudgetMax($budget);
        $this->em->persist($settings);
        $this->em->flush();

        return $settings;
    }

    protected function submitDrawRun(): void
    {
        $crawler = $this->client->request('GET', '/admin/draw');
        $formValues = ['confirm' => '1'];
        if ($crawler->filter('input[name="confirm_missing_wishes"]')->count() > 0) {
            $formValues['confirm_missing_wishes'] = '1';
        }
        $form = $crawler->selectButton('Lancer le tirage')->form($formValues);
        $this->client->submit($form);
    }
}
