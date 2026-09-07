<?php

namespace App\Tests\Unit;

use App\Exception\BudgetExceededException;
use App\Service\WishBudgetValidator;
use PHPUnit\Framework\TestCase;

final class WishBudgetValidatorTest extends TestCase
{
    public function testAcceptsPriceWithinBudget(): void
    {
        $validator = new WishBudgetValidator();
        $validator->assertWithinBudget(49.99, 50.0);
        $this->addToAssertionCount(1);
    }

    public function testAcceptsExactBudget(): void
    {
        $validator = new WishBudgetValidator();
        $validator->assertWithinBudget(50.0, 50.0);
        $this->addToAssertionCount(1);
    }

    public function testRejectsPriceAboveBudget(): void
    {
        $validator = new WishBudgetValidator();

        $this->expectException(BudgetExceededException::class);
        $this->expectExceptionMessage('50');

        $validator->assertWithinBudget(50.01, 50.0);
    }
}
