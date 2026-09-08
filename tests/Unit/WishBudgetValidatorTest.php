<?php

namespace App\Tests\Unit;

use App\Service\WishBudgetValidator;
use PHPUnit\Framework\TestCase;

final class WishBudgetValidatorTest extends TestCase
{
    public function testDetectsPriceWithinBudget(): void
    {
        $validator = new WishBudgetValidator();

        self::assertFalse($validator->isOverBudget(49.99, 50.0));
        self::assertFalse($validator->isOverBudget(50.0, 50.0));
    }

    public function testDetectsPriceAboveBudget(): void
    {
        $validator = new WishBudgetValidator();

        self::assertTrue($validator->isOverBudget(50.01, 50.0));
    }

    public function testFormatsAmount(): void
    {
        $validator = new WishBudgetValidator();

        self::assertSame('50', $validator->formatAmount(50.0));
        self::assertSame('50.5', $validator->formatAmount(50.5));
        self::assertSame('50.01', $validator->formatAmount(50.01));
    }
}
