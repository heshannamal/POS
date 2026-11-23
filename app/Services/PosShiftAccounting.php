<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PosShiftAccounting
{
    public function expected(int $shiftId, int $opening): int
    {
        $sales = (int) DB::table('pos_sale_payments as p')
            ->join('pos_sales as s','s.id','=','p.sale_id')
            ->where('s.shift_id',$shiftId)->where('s.status','completed')
            ->where('p.method','cash')->sum('p.amount_minor');
        $movements = (int) DB::table('pos_cash_movements')
            ->where('shift_id',$shiftId)->sum('amount_minor');
        return $opening + $sales + $movements;
    }
}