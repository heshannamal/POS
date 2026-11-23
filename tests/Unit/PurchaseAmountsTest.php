<?php

namespace Tests\Unit;

use App\Support\PurchaseAmounts;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PurchaseAmountsTest extends TestCase
{
    public function test_discount_allocates_proportionally_and_full_receipt_matches_total():void
    {
        $this->assertSame(2250,PurchaseAmounts::receivedValue(2500,10000,9000));
        $this->assertSame(9000,PurchaseAmounts::receivedValue(10000,10000,9000));
        $this->assertSame(0,PurchaseAmounts::receivedValue(0,10000,9000));
    }

    public function test_large_amounts_are_calculated_with_exact_integer_arithmetic():void
    {
        $this->assertSame(33333333333,PurchaseAmounts::receivedValue(50000000000,100000000000,66666666666));
        $this->assertSame(99999999999,PurchaseAmounts::receivedValue(100000000000,100000000000,99999999999));
    }

    public function test_zero_value_orders_are_valid():void
    {
        $this->assertSame(0,PurchaseAmounts::receivedValue(0,0,0));
    }

    public function test_received_value_cannot_exceed_ordered_value():void
    {
        $this->expectException(InvalidArgumentException::class);
        PurchaseAmounts::receivedValue(101,100,90);
    }
}
