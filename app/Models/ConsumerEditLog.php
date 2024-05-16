<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumerEditLog extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'swm_log_consumers';


    public function __construct($data = null)
    {
        // $this->connection = Session::get('ulb');
        $this->connection = $data;
    }
}
