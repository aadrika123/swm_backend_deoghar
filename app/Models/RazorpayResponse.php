<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RazorpayResponse extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = "db_swm";

    /**
     * | Create 
     */
    public function store($req)
    {
        return RazorpayResponse::create($req);
    }
}
