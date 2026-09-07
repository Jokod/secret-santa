<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateAdminCommandTest extends AppWebTestCase
{
    public function testCreatesNewAdmin(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:create-admin');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'email' => 'fresh-admin@example.com',
            'password' => 's3cret',
        ]);

        self::assertSame(0, $exitCode);
        $display = preg_replace('/\s+/', '', $tester->getDisplay()) ?? '';
        self::assertStringContainsString('fresh-admin@example.com', $display);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'fresh-admin@example.com']);
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertNotSame('s3cret', $user->getPassword());
    }

    public function testUpdatesExistingAdmin(): void
    {
        $existing = $this->createAdmin('existing-admin@example.com', 'old-password');
        $oldHash = $existing->getPassword();

        $application = new Application(self::$kernel);
        $command = $application->find('app:create-admin');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'email' => 'Existing-Admin@Example.com',
            'password' => 'new-password',
        ]);

        self::assertSame(0, $exitCode);

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'existing-admin@example.com']);
        self::assertNotNull($user);
        self::assertNotSame($oldHash, $user->getPassword());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }
}
