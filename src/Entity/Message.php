<?php

namespace App\Entity;

use App\Enum\MessageType;
use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    public const MAX_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Participant $santa;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Participant $target;

    #[ORM\Column(enumType: MessageType::class)]
    private MessageType $type;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: self::MAX_LENGTH)]
    private string $body = '';

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->type = MessageType::ToTarget;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSanta(): Participant
    {
        return $this->santa;
    }

    public function setSanta(Participant $santa): static
    {
        $this->santa = $santa;

        return $this;
    }

    public function getTarget(): Participant
    {
        return $this->target;
    }

    public function setTarget(Participant $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getType(): MessageType
    {
        return $this->type;
    }

    public function setType(MessageType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = trim($body);

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
