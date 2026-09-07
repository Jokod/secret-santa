<?php

namespace App\Entity;

use App\Repository\ParticipantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParticipantRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_participant_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_participant_name', columns: ['name'])]
#[ORM\UniqueConstraint(name: 'uniq_participant_token', columns: ['token_secret'])]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['name'], message: 'Ce nom est déjà utilisé.')]
class Participant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    #[ORM\Column(name: 'token_secret', length: 64)]
    private string $tokenSecret = '';

    /** @var Collection<int, Wish> */
    #[ORM\OneToMany(targetEntity: Wish::class, mappedBy: 'participant', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['preferenceOrder' => 'ASC'])]
    private Collection $wishes;

    #[ORM\OneToOne(mappedBy: 'santa', targetEntity: Assignment::class)]
    private ?Assignment $assignmentAsSanta = null;

    #[ORM\OneToOne(mappedBy: 'target', targetEntity: Assignment::class)]
    private ?Assignment $assignmentAsTarget = null;

    public function __construct()
    {
        $this->wishes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = self::normalizeName(trim($name));

        return $this;
    }

    /**
     * Trim already applied by caller. Capitalizes the first letter of each
     * space- or hyphen-separated part (e.g. "pierre-alain" → "Pierre-Alain").
     */
    private static function normalizeName(string $name): string
    {
        if ($name === '') {
            return '';
        }

        $name = mb_strtolower($name, 'UTF-8');
        $result = '';
        $capitalizeNext = true;
        $length = mb_strlen($name, 'UTF-8');

        for ($i = 0; $i < $length; ++$i) {
            $char = mb_substr($name, $i, 1, 'UTF-8');

            if ($capitalizeNext && preg_match('/\p{L}/u', $char) === 1) {
                $result .= mb_strtoupper($char, 'UTF-8');
                $capitalizeNext = false;
                continue;
            }

            $result .= $char;
            $capitalizeNext = preg_match('/[-\s]/u', $char) === 1;
        }

        return $result;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = strtolower(trim($email));

        return $this;
    }

    public function getTokenSecret(): string
    {
        return $this->tokenSecret;
    }

    public function setTokenSecret(string $tokenSecret): static
    {
        $this->tokenSecret = $tokenSecret;

        return $this;
    }

    /**
     * @return Collection<int, Wish>
     */
    public function getWishes(): Collection
    {
        return $this->wishes;
    }

    public function addWish(Wish $wish): static
    {
        if (!$this->wishes->contains($wish)) {
            $this->wishes->add($wish);
            $wish->setParticipant($this);
        }

        return $this;
    }

    public function removeWish(Wish $wish): static
    {
        if ($this->wishes->removeElement($wish) && $wish->getParticipant() === $this) {
            $wish->setParticipant(null);
        }

        return $this;
    }

    public function getAssignmentAsSanta(): ?Assignment
    {
        return $this->assignmentAsSanta;
    }

    public function setAssignmentAsSanta(?Assignment $assignmentAsSanta): static
    {
        if ($assignmentAsSanta === null && $this->assignmentAsSanta !== null) {
            $this->assignmentAsSanta->setSanta(null);
        }

        if ($assignmentAsSanta !== null && $assignmentAsSanta->getSanta() !== $this) {
            $assignmentAsSanta->setSanta($this);
        }

        $this->assignmentAsSanta = $assignmentAsSanta;

        return $this;
    }

    public function getAssignmentAsTarget(): ?Assignment
    {
        return $this->assignmentAsTarget;
    }

    public function setAssignmentAsTarget(?Assignment $assignmentAsTarget): static
    {
        if ($assignmentAsTarget === null && $this->assignmentAsTarget !== null) {
            $this->assignmentAsTarget->setTarget(null);
        }

        if ($assignmentAsTarget !== null && $assignmentAsTarget->getTarget() !== $this) {
            $assignmentAsTarget->setTarget($this);
        }

        $this->assignmentAsTarget = $assignmentAsTarget;

        return $this;
    }

    public function isAssigned(): bool
    {
        return $this->assignmentAsSanta !== null || $this->assignmentAsTarget !== null;
    }
}
