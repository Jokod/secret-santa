<?php

namespace App\Tests\Unit;

use App\Exception\EditionLockedException;
use App\Repository\AssignmentRepository;
use App\Service\EditionLockGuard;
use PHPUnit\Framework\TestCase;

final class EditionLockGuardTest extends TestCase
{
    public function testAllowsMutationsWhenDrawInactive(): void
    {
        $repo = $this->createMock(AssignmentRepository::class);
        $repo->expects(self::once())->method('hasActiveDraw')->willReturn(false);

        $guard = new EditionLockGuard($repo);
        $guard->assertMutable();
    }

    public function testBlocksMutationsWhenDrawActive(): void
    {
        $repo = $this->createMock(AssignmentRepository::class);
        $repo->expects(self::once())->method('hasActiveDraw')->willReturn(true);

        $guard = new EditionLockGuard($repo);

        $this->expectException(EditionLockedException::class);
        $this->expectExceptionMessage('tirage');

        $guard->assertMutable();
    }

    public function testIsLockedReflectsDrawState(): void
    {
        $unlockedRepo = $this->createMock(AssignmentRepository::class);
        $unlockedRepo->expects(self::once())->method('hasActiveDraw')->willReturn(false);
        $lockedRepo = $this->createMock(AssignmentRepository::class);
        $lockedRepo->expects(self::once())->method('hasActiveDraw')->willReturn(true);

        self::assertFalse((new EditionLockGuard($unlockedRepo))->isLocked());
        self::assertTrue((new EditionLockGuard($lockedRepo))->isLocked());
    }
}
