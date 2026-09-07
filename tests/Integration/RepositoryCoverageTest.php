<?php

namespace App\Tests\Integration;

use App\Entity\EditionSettings;
use App\Entity\Exclusion;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\MessageType;
use App\Repository\EditionSettingsRepository;
use App\Repository\ExclusionRepository;
use App\Repository\MessageRepository;
use App\Repository\ParticipantRepository;
use App\Repository\UserRepository;
use App\Tests\AppWebTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class RepositoryCoverageTest extends AppWebTestCase
{
    public function testEditionSettingsGetSettingsCreatesSingleton(): void
    {
        $existing = $this->em->getRepository(EditionSettings::class)->find(EditionSettings::SINGLETON_ID);
        if ($existing !== null) {
            $this->em->remove($existing);
            $this->em->flush();
            $this->em->clear();
        }

        /** @var EditionSettingsRepository $repo */
        $repo = static::getContainer()->get(EditionSettingsRepository::class);
        $created = $repo->getSettings();

        self::assertSame(EditionSettings::SINGLETON_ID, $created->getId());
        self::assertSame(50.0, $created->getBudgetMax());

        $again = $repo->getSettings();
        self::assertSame($created->getId(), $again->getId());
        self::assertSame(75.0, $again->setBudgetMax(75.0)->getBudgetMax());
        $this->em->flush();

        $this->em->clear();
        $found = $repo->getSettings();
        self::assertSame(75.0, $found->getBudgetMax());
    }

    public function testExclusionGetForbiddenTargetsMap(): void
    {
        $alice = $this->createParticipant('Alice Map', 'alice.map@test.com');
        $bob = $this->createParticipant('Bob Map', 'bob.map@test.com');
        $cara = $this->createParticipant('Cara Map', 'cara.map@test.com');

        $this->em->persist((new Exclusion())->setSource($alice)->setTarget($bob));
        $this->em->persist((new Exclusion())->setSource($alice)->setTarget($cara));
        $this->em->persist((new Exclusion())->setSource($bob)->setTarget($alice));
        $this->em->flush();

        /** @var ExclusionRepository $repo */
        $repo = static::getContainer()->get(ExclusionRepository::class);
        $map = $repo->getForbiddenTargetsMap();

        self::assertEqualsCanonicalizing([$bob->getId(), $cara->getId()], $map[$alice->getId()]);
        self::assertSame([$alice->getId()], $map[$bob->getId()]);
        self::assertArrayNotHasKey($cara->getId(), $map);
    }

    public function testExclusionFindAllWithParticipants(): void
    {
        $alice = $this->createParticipant('Alice List', 'alice.list@test.com');
        $bob = $this->createParticipant('Bob List', 'bob.list@test.com');
        $this->em->persist((new Exclusion())->setSource($alice)->setTarget($bob));
        $this->em->flush();

        /** @var ExclusionRepository $repo */
        $repo = static::getContainer()->get(ExclusionRepository::class);
        $rows = $repo->findAllWithParticipants();

        self::assertNotEmpty($rows);
        self::assertSame('Alice List', $rows[0]->getSource()->getName());
        self::assertSame('Bob List', $rows[0]->getTarget()->getName());
    }

    public function testMessageCountUnreadAndFindAllOrdered(): void
    {
        $santa = $this->createParticipant('Santa Msg', 'santa.msg@test.com');
        $target = $this->createParticipant('Target Msg', 'target.msg@test.com');

        $older = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('older')
            ->setIsRead(true);
        $newer = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToSanta)
            ->setBody('newer')
            ->setIsRead(false);

        $createdAt = new \ReflectionProperty(Message::class, 'createdAt');
        $createdAt->setValue($older, new \DateTimeImmutable('-2 minutes'));
        $createdAt->setValue($newer, new \DateTimeImmutable('-1 minute'));

        $this->em->persist($older);
        $this->em->persist($newer);
        $this->em->flush();

        /** @var MessageRepository $repo */
        $repo = static::getContainer()->get(MessageRepository::class);

        self::assertSame(1, $repo->countUnread());

        $ordered = $repo->findAllOrdered();
        self::assertGreaterThanOrEqual(2, count($ordered));
        self::assertSame('newer', $ordered[0]->getBody());

        $thread = $repo->findThread($santa, $target);
        self::assertCount(2, $thread);
        self::assertSame('older', $thread[0]->getBody());
        self::assertSame('newer', $thread[1]->getBody());

        $groups = $repo->findGroupedThreads();
        self::assertNotEmpty($groups);
        $matched = null;
        foreach ($groups as $group) {
            if ($group['santa']->getId() === $santa->getId() && $group['target']->getId() === $target->getId()) {
                $matched = $group;
                break;
            }
        }
        self::assertNotNull($matched);
        self::assertCount(2, $matched['messages']);
        self::assertSame('older', $matched['messages'][0]->getBody());
        self::assertSame(1, $matched['unreadCount']);
    }

    public function testParticipantFindOneByTokenAndOrderedByName(): void
    {
        $this->createParticipant('Zed Order', 'zed.order@test.com');
        $this->createParticipant('Ann Order', 'ann.order@test.com');
        $participant = $this->createParticipant('Token User', 'token.user@test.com');

        /** @var ParticipantRepository $repo */
        $repo = static::getContainer()->get(ParticipantRepository::class);

        self::assertSame($participant->getId(), $repo->findOneByToken($participant->getTokenSecret())?->getId());
        self::assertNull($repo->findOneByToken('missing-token'));

        $ordered = $repo->findAllOrderedByName();
        $names = array_map(static fn ($p) => $p->getName(), $ordered);
        $annPos = array_search('Ann Order', $names, true);
        $zedPos = array_search('Zed Order', $names, true);
        self::assertNotFalse($annPos);
        self::assertNotFalse($zedPos);
        self::assertLessThan($zedPos, $annPos);

        $without = $repo->findWithoutWishes();
        $withoutNames = array_map(static fn ($p) => $p->getName(), $without);
        self::assertContains('Ann Order', $withoutNames);

        $wish = (new \App\Entity\Wish())->setTitle('Book')->setEstimatedPrice(10)->setPreferenceOrder(1);
        $participant->addWish($wish);
        $this->em->flush();

        $withoutAfter = array_map(static fn ($p) => $p->getName(), $repo->findWithoutWishes());
        self::assertNotContains('Token User', $withoutAfter);
    }

    public function testUserUpgradePasswordSuccessAndUnsupported(): void
    {
        $user = $this->createAdmin('upgrade@test.com', 'old-password');

        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        $repo->upgradePassword($user, 'new-hash');

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->findOneBy(['email' => 'upgrade@test.com']);
        self::assertNotNull($reloaded);
        self::assertSame('new-hash', $reloaded->getPassword());

        $foreign = new class implements PasswordAuthenticatedUserInterface {
            public function getPassword(): ?string
            {
                return 'x';
            }
        };

        $this->expectException(UnsupportedUserException::class);
        $repo->upgradePassword($foreign, 'noop');
    }
}
