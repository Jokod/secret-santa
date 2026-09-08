<?php

namespace App\Entity;

use App\Repository\EditionSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EditionSettingsRepository::class)]
class EditionSettings
{
    public const SINGLETON_ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::SINGLETON_ID;

    #[ORM\Column]
    #[Assert\Positive]
    private float $budgetMax = 50.0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $eventDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $drawDate = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $welcomeEmailTemplate = "Bonjour {SANTA},\n\nBienvenue dans le Secret Santa familial ! Budget : {BUDGET} €.\nTon lien personnel : {LINK}";

    #[ORM\Column(type: Types::TEXT)]
    private string $resultEmailTemplate = "Bonjour {SANTA},\n\nTu offres un cadeau à {TARGET} (budget {BUDGET} €).\nRetrouve ton espace : {LINK}";

    #[ORM\Column(type: Types::TEXT)]
    private string $reminderEmailTemplate = "Bonjour {SANTA},\n\nPense à remplir ta liste de souhaits (budget {BUDGET} €).\nTon espace : {LINK}";

    public function getId(): int
    {
        return $this->id;
    }

    public function getBudgetMax(): float
    {
        return $this->budgetMax;
    }

    public function setBudgetMax(float $budgetMax): static
    {
        $this->budgetMax = $budgetMax;

        return $this;
    }

    public function getEventDate(): ?\DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(?\DateTimeImmutable $eventDate): static
    {
        $this->eventDate = $eventDate;

        return $this;
    }

    public function getDrawDate(): ?\DateTimeImmutable
    {
        return $this->drawDate;
    }

    public function setDrawDate(?\DateTimeImmutable $drawDate): static
    {
        $this->drawDate = $drawDate;

        return $this;
    }

    public function getWelcomeEmailTemplate(): string
    {
        return $this->welcomeEmailTemplate;
    }

    public function setWelcomeEmailTemplate(string $welcomeEmailTemplate): static
    {
        $this->welcomeEmailTemplate = $welcomeEmailTemplate;

        return $this;
    }

    public function getResultEmailTemplate(): string
    {
        return $this->resultEmailTemplate;
    }

    public function setResultEmailTemplate(string $resultEmailTemplate): static
    {
        $this->resultEmailTemplate = $resultEmailTemplate;

        return $this;
    }

    public function getReminderEmailTemplate(): string
    {
        return $this->reminderEmailTemplate;
    }

    public function setReminderEmailTemplate(string $reminderEmailTemplate): static
    {
        $this->reminderEmailTemplate = $reminderEmailTemplate;

        return $this;
    }
}
