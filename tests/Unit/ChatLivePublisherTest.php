<?php

namespace App\Tests\Unit;

use App\Entity\Message;
use App\Entity\Participant;
use App\Enum\MessageType;
use App\Service\ChatLivePublisher;
use App\Service\ChatThreadTopic;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

final class ChatLivePublisherTest extends TestCase
{
    public function testPublishSendsPrivateNeutralJson(): void
    {
        /** @var list<Update> $updates */
        $updates = [];
        $hub = new MockHub(
            'http://hub.test/.well-known/mercure',
            new StaticTokenProvider('token'),
            static function (Update $update) use (&$updates): string {
                $updates[] = $update;

                return 'ok';
            },
        );

        $publisher = new ChatLivePublisher(
            $hub,
            new ChatThreadTopic('secret-for-tests'),
            $this->createStub(LoggerInterface::class),
        );

        $santa = $this->participant(10, 'Santa');
        $target = $this->participant(20, 'Target');
        $message = (new Message())
            ->setSanta($santa)
            ->setTarget($target)
            ->setType(MessageType::ToTarget)
            ->setBody('Coucou live');
        $idRef = new \ReflectionProperty(Message::class, 'id');
        $idRef->setValue($message, 99);

        $publisher->publish($message);

        self::assertCount(1, $updates);
        self::assertTrue($updates[0]->isPrivate());
        self::assertSame(
            [(new ChatThreadTopic('secret-for-tests'))->forPair($santa, $target)],
            $updates[0]->getTopics(),
        );

        $payload = json_decode($updates[0]->getData(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(99, $payload['id']);
        self::assertSame('to_target', $payload['type']);
        self::assertSame('Coucou live', $payload['body']);
        self::assertArrayHasKey('createdAt', $payload);
        self::assertStringNotContainsString('Santa', $updates[0]->getData());
        self::assertStringNotContainsString('Target', $updates[0]->getData());
    }

    public function testPublishWithoutIdIsNoop(): void
    {
        $hub = new MockHub(
            'http://hub.test/.well-known/mercure',
            new StaticTokenProvider('token'),
            static function (): string {
                self::fail('Should not publish');
            },
        );

        $publisher = new ChatLivePublisher(
            $hub,
            new ChatThreadTopic('secret-for-tests'),
            $this->createStub(LoggerInterface::class),
        );

        $message = (new Message())
            ->setSanta($this->participant(1, 'A'))
            ->setTarget($this->participant(2, 'B'))
            ->setType(MessageType::ToSanta)
            ->setBody('x');

        $publisher->publish($message);
        self::assertTrue(true);
    }

    public function testPayloadForRequiresPersistedMessage(): void
    {
        $publisher = new ChatLivePublisher(
            new MockHub('http://hub.test/.well-known/mercure', new StaticTokenProvider('token'), static fn (): string => 'ok'),
            new ChatThreadTopic('secret-for-tests'),
            $this->createStub(LoggerInterface::class),
        );

        $message = (new Message())
            ->setSanta($this->participant(1, 'A'))
            ->setTarget($this->participant(2, 'B'))
            ->setType(MessageType::ToSanta)
            ->setBody('x');

        $this->expectException(\LogicException::class);
        $publisher->payloadFor($message);
    }

    public function testPayloadForIsNeutral(): void
    {
        $publisher = new ChatLivePublisher(
            new MockHub('http://hub.test/.well-known/mercure', new StaticTokenProvider('token'), static fn (): string => 'ok'),
            new ChatThreadTopic('secret-for-tests'),
            $this->createStub(LoggerInterface::class),
        );

        $message = (new Message())
            ->setSanta($this->participant(1, 'Alice'))
            ->setTarget($this->participant(2, 'Bob'))
            ->setType(MessageType::ToTarget)
            ->setBody('Hi');
        (new \ReflectionProperty(Message::class, 'id'))->setValue($message, 5);

        $payload = $publisher->payloadFor($message);
        self::assertSame(5, $payload['id']);
        self::assertSame('to_target', $payload['type']);
        self::assertSame('Hi', $payload['body']);
        self::assertArrayHasKey('createdAt', $payload);
    }

    public function testPublishFailureIsLoggedNotThrown(): void
    {
        $hub = new MockHub(
            'http://hub.test/.well-known/mercure',
            new StaticTokenProvider('token'),
            static function (): string {
                throw new \RuntimeException('hub down');
            },
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $publisher = new ChatLivePublisher(
            $hub,
            new ChatThreadTopic('secret-for-tests'),
            $logger,
        );

        $message = (new Message())
            ->setSanta($this->participant(1, 'A'))
            ->setTarget($this->participant(2, 'B'))
            ->setType(MessageType::ToSanta)
            ->setBody('x');
        (new \ReflectionProperty(Message::class, 'id'))->setValue($message, 1);

        $publisher->publish($message);
    }

    private function participant(int $id, string $name): Participant
    {
        $p = (new Participant())
            ->setName($name)
            ->setEmail(strtolower($name).'@t.com')
            ->setTokenSecret(str_repeat('x', 64));
        (new \ReflectionProperty(Participant::class, 'id'))->setValue($p, $id);

        return $p;
    }
}
