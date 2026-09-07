<?php

namespace App\Service;

use App\Exception\EditionLockedException;
use App\Repository\AssignmentRepository;

final class EditionLockGuard
{
    public function __construct(
        private readonly AssignmentRepository $assignmentRepository,
    ) {
    }

    public function isLocked(): bool
    {
        return $this->assignmentRepository->hasActiveDraw();
    }

    public function assertMutable(): void
    {
        if ($this->isLocked()) {
            throw new EditionLockedException(
                'Le tirage est déjà lancé : ces réglages sont verrouillés jusqu’à un reset conscient.'
            );
        }
    }
}
