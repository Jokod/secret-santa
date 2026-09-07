<?php

namespace App\Entity;

use App\Repository\ExclusionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExclusionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_exclusion_pair', columns: ['source_id', 'target_id'])]
class Exclusion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Participant $source;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Participant $target;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): Participant
    {
        return $this->source;
    }

    public function setSource(Participant $source): static
    {
        $this->source = $source;

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
}
