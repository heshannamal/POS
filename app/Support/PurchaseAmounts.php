<?php

namespace App\Support;

use InvalidArgumentException;

final class PurchaseAmounts
{
    /** Exact floor(receivedGross * orderTotal / subtotal) without overflow. */
    public static function receivedValue(int $receivedGross,int $subtotal,int $orderTotal):int
    {
        if($receivedGross<0 || $subtotal<0 || $orderTotal<0 || $orderTotal>$subtotal
            || $receivedGross>$subtotal || ($subtotal===0 && $receivedGross>0)){
            throw new InvalidArgumentException('Invalid purchase amounts.');
        }
        if($subtotal===0)return 0;
        $quotient=0;
        $remainder=0;
        foreach(str_split(decbin($receivedGross)) as $bit){
            // remainder < subtotal. PO monetary values are capped at 1e11.
            $value=$remainder*2+((int)$bit)*$orderTotal;
            $quotient=$quotient*2+intdiv($value,$subtotal);
            $remainder=$value%$subtotal;
        }
        return $quotient;
    }
}
