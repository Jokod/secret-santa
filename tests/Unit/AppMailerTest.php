<?php

namespace App\Tests\Unit;

use App\Entity\Assignment;
use App\Entity\EditionSettings;
use App\Entity\Participant;
use App\Enum\MessageType;
use App\Repository\EditionSettingsRepository;
use App\Service\AppMailer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AppMailerTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private AppMailer $appMailer;
    private EditionSettings $settings;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('generate')->willReturn('/p/token');

        $this->settings = (new EditionSettings())
            ->setBudgetMax(50.0)
            ->setWelcomeEmailTemplate('Welcome {SANTA} budget {BUDGET} link {LINK}')
            ->setResultEmailTemplate('Result {SANTA} -> {TARGET} {BUDGET} {LINK}')
            ->setReminderEmailTemplate('Reminder {SANTA} {BUDGET} {LINK}');

        $settingsRepository = $this->createStub(EditionSettingsRepository::class);
        $settingsRepository->method('getSettings')->willReturn($this->settings);

        $this->appMailer = new AppMailer(
            $this->mailer,
            $this->urlGenerator,
            $settingsRepository,
            'noreply@santa.test',
            'https://santa.test',
        );
    }

    public function testSendWelcome(): void
    {
        $participant = $this->participant('Alice', 'alice@test.com', 'tok-a');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertSame('Bienvenue au Secret Santa', $email->getSubject());
                self::assertStringContainsString('alice@test.com', $email->getTo()[0]->getAddress());
                self::assertStringContainsString('Welcome Alice', (string) $email->getTextBody());
                self::assertStringContainsString('https://santa.test/p/token', (string) $email->getTextBody());

                self::assertInstanceOf(TemplatedEmail::class, $email);
                $htmlBody = (string) $email->getContext()['body'];
                self::assertStringContainsString('<a href="https://santa.test/p/token"', $htmlBody);
                self::assertStringContainsString('Accéder à mon espace</a>', $htmlBody);
                self::assertStringContainsString('<strong>Alice</strong>', $htmlBody);
                self::assertStringContainsString('<strong>50</strong>', $htmlBody);
                self::assertStringNotContainsString('https://santa.test/p/token</a>', $htmlBody);

                return true;
            }));

        $this->appMailer->sendWelcome($participant);
    }

    public function testSendDrawResult(): void
    {
        $santa = $this->participant('Alice', 'alice@test.com', 'tok-a');
        $target = $this->participant('Bob', 'bob@test.com', 'tok-b');
        $assignment = (new Assignment())->setSanta($santa)->setTarget($target);

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertSame('Résultat du tirage Secret Santa', $email->getSubject());
                self::assertStringContainsString('Result Alice -> Bob', (string) $email->getTextBody());
                self::assertInstanceOf(TemplatedEmail::class, $email);
                $htmlBody = (string) $email->getContext()['body'];
                self::assertStringContainsString('<strong>Alice</strong>', $htmlBody);
                self::assertStringContainsString('<strong>Bob</strong>', $htmlBody);

                return true;
            }));

        $this->appMailer->sendDrawResult($assignment);
    }

    public function testSendDrawResultReturnsEarlyWhenSantaMissing(): void
    {
        $target = $this->participant('Bob', 'bob@test.com', 'tok-b');
        $assignment = (new Assignment())->setTarget($target);

        $this->mailer->expects(self::never())->method('send');

        $this->appMailer->sendDrawResult($assignment);
    }

    public function testSendDrawResultReturnsEarlyWhenTargetMissing(): void
    {
        $santa = $this->participant('Alice', 'alice@test.com', 'tok-a');
        $assignment = (new Assignment())->setSanta($santa);

        $this->mailer->expects(self::never())->method('send');

        $this->appMailer->sendDrawResult($assignment);
    }

    public function testSendWishReminder(): void
    {
        $participant = $this->participant('Cara', 'cara@test.com', 'tok-c');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertSame('Rappel : ta liste de souhaits', $email->getSubject());
                self::assertStringContainsString('Reminder Cara', (string) $email->getTextBody());

                return true;
            }));

        $this->appMailer->sendWishReminder($participant);
    }

    public function testSendMessageNotificationSingle(): void
    {
        $target = $this->participant('Bob', 'bob@test.com', 'tok-b');
        $mailer = $this->createMailerWithRouteMap([
            'participant_messages_santa|tok-b' => '/participant/tok-b/messages/santa',
        ]);

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) use ($target): bool {
                self::assertSame('Nouveau message Secret Santa', $email->getSubject());
                self::assertSame($target->getEmail(), $email->getTo()[0]->getAddress());
                self::assertStringContainsString('nouveau message Secret Santa', (string) $email->getTextBody());
                self::assertStringContainsString('/participant/tok-b/messages/santa', (string) $email->getTextBody());

                return true;
            }));

        $mailer->sendMessageNotification($target, 1, MessageType::ToTarget);
    }

    public function testSendMessageNotificationDigest(): void
    {
        $santa = $this->participant('Alice', 'alice@test.com', 'tok-a');
        $mailer = $this->createMailerWithRouteMap([
            'participant_messages_target|tok-a' => '/participant/tok-a/messages/target',
        ]);

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) use ($santa): bool {
                self::assertSame('3 nouveaux messages Secret Santa', $email->getSubject());
                self::assertSame($santa->getEmail(), $email->getTo()[0]->getAddress());
                self::assertStringContainsString('3 nouveaux messages', (string) $email->getTextBody());
                self::assertStringContainsString('/participant/tok-a/messages/target', (string) $email->getTextBody());

                return true;
            }));

        $mailer->sendMessageNotification($santa, 3, MessageType::ToSanta);
    }

    /**
     * @param array<string, string> $routeMap keys "route|token"
     */
    private function createMailerWithRouteMap(array $routeMap): AppMailer
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $params) use ($routeMap): string {
                $key = $route.'|'.($params['token'] ?? '');

                return $routeMap[$key] ?? '/p/token';
            }
        );

        $settingsRepository = $this->createStub(EditionSettingsRepository::class);
        $settingsRepository->method('getSettings')->willReturn($this->settings);

        return new AppMailer(
            $this->mailer,
            $urlGenerator,
            $settingsRepository,
            'noreply@santa.test',
            'https://santa.test',
        );
    }

    public function testBudgetFormattingStripsTrailingZeros(): void
    {
        $this->settings->setBudgetMax(50.50);
        $participant = $this->participant('Dan', 'dan@test.com', 'tok-d');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertStringContainsString('budget 50.5', (string) $email->getTextBody());

                return true;
            }));

        $this->appMailer->sendWelcome($participant);
    }

    private function participant(string $name, string $email, string $token): Participant
    {
        return (new Participant())
            ->setName($name)
            ->setEmail($email)
            ->setTokenSecret($token);
    }
}
