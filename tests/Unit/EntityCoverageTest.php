<?php

namespace App\Tests\Unit;

use App\Entity\Assignment;
use App\Entity\EditionSettings;
use App\Entity\Exclusion;
use App\Entity\Message;
use App\Entity\Participant;
use App\Entity\User;
use App\Entity\Wish;
use App\Enum\MessageType;
use PHPUnit\Framework\TestCase;

final class EntityCoverageTest extends TestCase
{
    public function testAssignmentGettersSettersAndBidirectionalLinks(): void
    {
        $assignment = new Assignment();
        self::assertNull($assignment->getId());
        self::assertNull($assignment->getSanta());
        self::assertNull($assignment->getTarget());

        $santa = $this->participant('Santa');
        $target = $this->participant('Target');

        $assignment->setSanta($santa)->setTarget($target);

        self::assertSame($santa, $assignment->getSanta());
        self::assertSame($target, $assignment->getTarget());
        self::assertSame($assignment, $santa->getAssignmentAsSanta());
        self::assertSame($assignment, $target->getAssignmentAsTarget());

        // Re-setting the same participant should not recurse infinitely
        $assignment->setSanta($santa)->setTarget($target);
        self::assertSame($santa, $assignment->getSanta());
    }

    public function testAssignmentClearingViaNull(): void
    {
        $assignment = new Assignment();
        $santa = $this->participant('Santa');
        $target = $this->participant('Target');
        $assignment->setSanta($santa)->setTarget($target);

        $assignment->setSanta(null);
        $assignment->setTarget(null);

        self::assertNull($assignment->getSanta());
        self::assertNull($assignment->getTarget());
    }

    public function testEditionSettingsAllAccessors(): void
    {
        $settings = new EditionSettings();
        self::assertSame(EditionSettings::SINGLETON_ID, $settings->getId());
        self::assertSame(50.0, $settings->getBudgetMax());
        self::assertNull($settings->getEventDate());
        self::assertNotSame('', $settings->getWelcomeEmailTemplate());
        self::assertNotSame('', $settings->getResultEmailTemplate());
        self::assertNotSame('', $settings->getReminderEmailTemplate());

        $date = new \DateTimeImmutable('2026-12-24');
        $settings
            ->setBudgetMax(75.5)
            ->setEventDate($date)
            ->setWelcomeEmailTemplate('welcome')
            ->setResultEmailTemplate('result')
            ->setReminderEmailTemplate('reminder');

        self::assertSame(75.5, $settings->getBudgetMax());
        self::assertSame($date, $settings->getEventDate());
        self::assertSame('welcome', $settings->getWelcomeEmailTemplate());
        self::assertSame('result', $settings->getResultEmailTemplate());
        self::assertSame('reminder', $settings->getReminderEmailTemplate());

        $settings->setEventDate(null);
        self::assertNull($settings->getEventDate());
    }

    public function testExclusionAccessors(): void
    {
        $source = $this->participant('A');
        $target = $this->participant('B');
        $exclusion = (new Exclusion())->setSource($source)->setTarget($target);

        self::assertNull($exclusion->getId());
        self::assertSame($source, $exclusion->getSource());
        self::assertSame($target, $exclusion->getTarget());
    }

    public function testMessageAccessorsIncludingIsRead(): void
    {
        $santa = $this->participant('Santa');
        $target = $this->participant('Target');
        $message = new Message();

        self::assertNull($message->getId());
        self::assertSame(MessageType::ToTarget, $message->getType());
        self::assertSame('', $message->getBody());
        self::assertFalse($message->isRead());
        self::assertInstanceOf(\DateTimeImmutable::class, $message->getCreatedAt());

        $message
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToSanta)
            ->setBody('  hello world  ')
            ->setIsRead(true);

        self::assertSame($santa, $message->getSanta());
        self::assertSame($target, $message->getTarget());
        self::assertSame(MessageType::ToSanta, $message->getType());
        self::assertSame('hello world', $message->getBody());
        self::assertTrue($message->isRead());

        $message->setIsRead(false);
        self::assertFalse($message->isRead());
    }

    public function testParticipantBasicsAndWishManagement(): void
    {
        $participant = new Participant();
        self::assertNull($participant->getId());
        self::assertFalse($participant->isAssigned());
        self::assertCount(0, $participant->getWishes());

        $participant
            ->setName('  Alice  ')
            ->setEmail('  Alice@Example.COM  ')
            ->setTokenSecret('secret-token');

        self::assertSame('Alice', $participant->getName());
        self::assertSame('alice@example.com', $participant->getEmail());
        self::assertSame('secret-token', $participant->getTokenSecret());

        $participant->setName('  pierre-alain  ');
        self::assertSame('Pierre-Alain', $participant->getName());

        $participant->setName('MARIE claire');
        self::assertSame('Marie Claire', $participant->getName());

        $participant->setName('élodie');
        self::assertSame('Élodie', $participant->getName());

        $participant->setName('   ');
        self::assertSame('', $participant->getName());

        // Re-applying setName on an already-stored lowercase value (form SUBMIT path)
        $nameProp = new \ReflectionProperty(Participant::class, 'name');
        $nameProp->setValue($participant, 'leo');
        self::assertSame('leo', $participant->getName());
        $participant->setName($participant->getName());
        self::assertSame('Leo', $participant->getName());

        $wish = (new Wish())->setTitle('Mug')->setEstimatedPrice(10)->setPreferenceOrder(1);
        $participant->addWish($wish);
        self::assertCount(1, $participant->getWishes());
        self::assertSame($participant, $wish->getParticipant());

        // Adding twice is idempotent
        $participant->addWish($wish);
        self::assertCount(1, $participant->getWishes());

        $participant->removeWish($wish);
        self::assertCount(0, $participant->getWishes());
        self::assertNull($wish->getParticipant());

        // Removing a wish not in the collection is a no-op
        $other = (new Wish())->setTitle('Book')->setParticipant($this->participant('Other'));
        $participant->removeWish($other);
        self::assertSame('Other', $other->getParticipant()?->getName());
    }

    public function testParticipantAssignmentLinksAndNullClearing(): void
    {
        $santa = $this->participant('Santa');
        $target = $this->participant('Target');
        $assignment = new Assignment();

        $santa->setAssignmentAsSanta($assignment);
        $target->setAssignmentAsTarget($assignment);

        self::assertSame($assignment, $santa->getAssignmentAsSanta());
        self::assertSame($assignment, $target->getAssignmentAsTarget());
        self::assertSame($santa, $assignment->getSanta());
        self::assertSame($target, $assignment->getTarget());
        self::assertTrue($santa->isAssigned());
        self::assertTrue($target->isAssigned());

        // Setting same assignment again covers already-linked branches
        $santa->setAssignmentAsSanta($assignment);
        $target->setAssignmentAsTarget($assignment);

        $santa->setAssignmentAsSanta(null);
        $target->setAssignmentAsTarget(null);

        self::assertNull($santa->getAssignmentAsSanta());
        self::assertNull($target->getAssignmentAsTarget());
        self::assertNull($assignment->getSanta());
        self::assertNull($assignment->getTarget());
        self::assertFalse($santa->isAssigned());
        self::assertFalse($target->isAssigned());

        // Clearing when already null
        $santa->setAssignmentAsSanta(null);
        $target->setAssignmentAsTarget(null);
        self::assertFalse($santa->isAssigned());
    }

    public function testUserAccessorsRolesAndEraseCredentials(): void
    {
        $user = new User();
        self::assertNull($user->getId());
        self::assertSame('', $user->getEmail());
        self::assertSame('', $user->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame('', $user->getPassword());

        $user
            ->setEmail('  Admin@Example.COM  ')
            ->setRoles(['ROLE_ADMIN', 'ROLE_USER'])
            ->setPassword('hashed');

        self::assertSame('admin@example.com', $user->getEmail());
        self::assertSame('admin@example.com', $user->getUserIdentifier());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
        self::assertSame('hashed', $user->getPassword());

        $user->eraseCredentials();
        self::assertSame('hashed', $user->getPassword());
    }

    public function testWishAllAccessorsIncludingEmptyUrl(): void
    {
        $wish = new Wish();
        self::assertNull($wish->getId());
        self::assertNull($wish->getParticipant());
        self::assertSame('', $wish->getTitle());
        self::assertNull($wish->getDescription());
        self::assertNull($wish->getUrl());
        self::assertSame(0.0, $wish->getEstimatedPrice());
        self::assertSame(0, $wish->getPreferenceOrder());

        $participant = $this->participant('Owner');
        $wish
            ->setParticipant($participant)
            ->setTitle('  Gift  ')
            ->setDescription('Nice')
            ->setUrl('https://example.com/gift')
            ->setEstimatedPrice(12.5)
            ->setPreferenceOrder(2);

        self::assertSame($participant, $wish->getParticipant());
        self::assertSame('Gift', $wish->getTitle());
        self::assertSame('Nice', $wish->getDescription());
        self::assertSame('https://example.com/gift', $wish->getUrl());
        self::assertSame(12.5, $wish->getEstimatedPrice());
        self::assertSame(2, $wish->getPreferenceOrder());

        $wish->setUrl('');
        self::assertNull($wish->getUrl());
        $wish->setUrl(null);
        self::assertNull($wish->getUrl());
        $wish->setDescription(null);
        self::assertNull($wish->getDescription());
        $wish->setParticipant(null);
        self::assertNull($wish->getParticipant());
    }

    private function participant(string $name): Participant
    {
        return (new Participant())
            ->setName($name)
            ->setEmail(strtolower($name).'@test.com')
            ->setTokenSecret('tok-'.strtolower($name));
    }
}
