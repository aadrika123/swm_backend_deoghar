<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repository\ApartmentRepository;

class ApartmentController extends Controller
{
    /**
     * | Created On- 08-09-2022 
     * | Created By- 
     * | Controller Consumer related operations
     */

    //  Initializing construct function for Repositoy
    protected $appartment;
    public function __construct(ApartmentRepository $appartment)
    {
        $this->Appart = $appartment;

    }


    public function GetApartmentList(Request $req)
    {
        return $this->Appart->ApartmentList($req);
        echo "hello";
    }
}
