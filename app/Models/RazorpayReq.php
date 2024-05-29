<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RazorpayReq extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = "db_swm";

    /**
     * | Create 
     */
    public function store($req)
    {
        return RazorpayReq::create($req);
    }

    /**
     * |
     */
    public function getPaymentRecord($req)
    {
        return RazorpayReq::where('order_id', $req->orderId)
            // ->where('consumer_id', $req->consumerId)
            ->where('payment_status', 0);
            // ->first();
    }
}
