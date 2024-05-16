<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Routes extends Model
{
    use HasFactory;
    protected $connection;
    protected $table = 'swm_routes';

    public function __construct($data = null)
    {
        $this->connection = $data;
    }
}
