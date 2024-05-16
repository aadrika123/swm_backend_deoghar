<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandLog extends Model
{
    use HasFactory;
    protected $connection;
    public $timestamps = false;
    protected $table = 'swm_log_demand_receipts';

    public function __construct($data = null)
    {
        //$this->connection = Session::get('ulb');
        $this->connection = $data;
    }
}
