<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandAdjustment extends Model
{
    use HasFactory;
    protected $connection;
    public $timestamps = false;
    protected $table = 'swm_demand_adjustments';

    public function __construct($data = null)
    {
        //$this->connection = Session::get('ulb');
        $this->connection = $data;
    }
}
