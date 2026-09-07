<?php

namespace App\Service;

use App\Exception\BudgetExceededException;

final class WishBudgetValidator
{
    public function assertWithinBudget(float $price, float $budgetMax): void
    {
        if ($price > $budgetMax) {
            throw new BudgetExceededException(sprintf(
                'Le prix estimé dépasse le budget maximum de %s €.',
                rtrim(rtrim(number_format($budgetMax, 2, '.', ''), '0'), '.')
            ));
        }
    }
}
