<?php

namespace App\Entity;

use App\Repository\AssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssignmentRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_assignment_santa', columns: ['santa_id'])]
#[ORM\UniqueConstraint(name: 'uniq_assignment_target', columns: ['target_id'])]
class Assignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'assignmentAsSanta')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Participant $santa = null;

    #[ORM\OneToOne(inversedBy: 'assignmentAsTarget')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Participant $target = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSanta(): ?Participant
    {
        return $this->santa;
    }

    public function setSanta(?Participant $santa): static
    {
        $this->santa = $santa;
        if ($santa !== null && $santa->getAssignmentAsSanta() !== $this) {
            $santa->setAssignmentAsSanta($this);
        }

        return $this;
    }

    public function getTarget(): ?Participant
    {
        return $this->target;
    }

    public function setTarget(?Participant $target): static
    {
        $this->target = $target;
        if ($target !== null && $target->getAssignmentAsTarget() !== $this) {
            $target->setAssignmentAsTarget($this);
        }

        return $this;
    }
}
