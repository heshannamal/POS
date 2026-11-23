<?php

namespace App\Support;

use InvalidArgumentException;

final class PurchaseAmounts
{
    /** Allocation of the negotiated PO total to received items (minor currency units). */
    public static function receivedValue(int $receivedGross,int $subtotal,int $orderTotal):int
    {
        if($receivedGross<0 || $subtotal<0 || $orderTotal<0 || $orderTotal>$subtotal
            || $receivedGross>$subtotal || ($subtotal===0 && $receivedGross>0)){
            throw new InvalidArgumentException('Invalid purchase amounts.');
        }
        if($subtotal===0)return 0;
        // POs are capped at 100_000_000_000 minor units, making integer multiplication safe.
        return intdiv($receivedGross*$orderTotal,$subtotal);
    }
}
