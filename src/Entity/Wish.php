<?php

namespace App\Entity;

use App\Repository\WishRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WishRepository::class)]
class Wish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'wishes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Participant $participant = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url(requireTld: false)]
    private ?string $url = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private float $estimatedPrice = 0.0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $preferenceOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipant(): ?Participant
    {
        return $this->participant;
    }

    public function setParticipant(?Participant $participant): static
    {
        $this->participant = $participant;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url ?: null;

        return $this;
    }

    public function getEstimatedPrice(): float
    {
        return $this->estimatedPrice;
    }

    public function setEstimatedPrice(float $estimatedPrice): static
    {
        $this->estimatedPrice = $estimatedPrice;

        return $this;
    }

    public function getPreferenceOrder(): int
    {
        return $this->preferenceOrder;
    }

    public function setPreferenceOrder(int $preferenceOrder): static
    {
        $this->preferenceOrder = $preferenceOrder;

        return $this;
    }
}
