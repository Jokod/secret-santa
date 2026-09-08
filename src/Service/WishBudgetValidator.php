<?php

namespace App\Service;

final class WishBudgetValidator
{
    public function isOverBudget(float $price, float $budgetMax): bool
    {
        return $price > $budgetMax;
    }

    public function formatAmount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') ?: '0';
    }
}
