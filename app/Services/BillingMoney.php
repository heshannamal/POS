<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class BillingMoney
{
    public static function parse(string $major): int
    {
        $major = trim($major);
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $major)) {
            throw ValidationException::withMessages(['amount'=>'Enter a valid monetary amount (up to two decimals).']);
        }
        [$whole,$fraction] = array_pad(explode('.',$major,2),2,'');
        return ((int)$whole * 100) + (int) str_pad($fraction,2,'0');
    }

    public static function format(int $minor): string
    {
        return number_format($minor / 100,2,'.',',');
    }

    public static function percentage(int $amountMinor, int $basisPoints): int
    {
        return intdiv($amountMinor * $basisPoints + 5000, 10000);
    }
}
