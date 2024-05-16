<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repository\iMasterRepository;
use Exception;

class MasterController extends Controller
{
    /**
     * | Created On- 08-09-2022 
     * | Created By- 
     * | Controller Consumer related operations
     */

    //  Initializing construct function for Repositoy
    protected $master;
    public function __construct(iMasterRepository $master)
    {
        $this->Mstr = $master;
    }

    public function GetConsumerAddFormData(Request $req)
    {
        return $this->Mstr->getConsumerFormDate($req);
    }

    public function GetApartmentListData(Request $req)
    {
        return $this->Mstr->getApartmentList($req);
    }

    public function getApartmentById(Request $req)
    {
        return $this->Mstr->getApartmentById($req);
    }


    public function GetConsumerTypeByCategoryId(Request $req)
    {
        return $this->Mstr->GetConsumerTypeByCategoryId($req);
    }

    public function updateApartment(Request $req)
    {
        return $this->Mstr->updateApartment($req);
    }

    public function addApartment(Request $req)
    {
        return $this->Mstr->addApartment($req);
    }

    public function getConsumerCategoryList(Request $req)
    {
        return $this->Mstr->getConsumerCategoryList($req);
    }

    public function ConsumerCategoryAdd(Request $req)
    {
        return $this->Mstr->ConsumerCategoryAdd($req);
    }

    public function ConsumerCategoryUpdate(Request $req)
    {
        return $this->Mstr->ConsumerCategoryUpdate($req);
    }

    public function ConsumerCategoryById(Request $req)
    {
        return $this->Mstr->ConsumerCategoryById($req);
    }


    public function ConsumerTypeList(Request $req)
    {
        return $this->Mstr->GetConsumerTypeList($req);
    }

    public function ConsumerTypeAdd(Request $req)
    {
        return $this->Mstr->ConsumerTypeAdd($req);
    }

    public function ConsumerTypeUpdate(Request $req)
    {
        return $this->Mstr->ConsumerTypeUpdate($req);
    }

    public function ConsumerTypeById(Request $req)
    {
        return $this->Mstr->ConsumerTypeById($req);
    }

    public function UlbList(Request $req)
    {
        return $this->Mstr->UlbList($req);
    }

    public function UlbAdd(Request $req)
    {
        return $this->Mstr->UlbAdd($req);
    }

    public function UlbUpdate(Request $req)
    {
        return $this->Mstr->UlbUpdate($req);
    }

    public function UlbActiveDeactive(Request $req)
    {
        return $this->Mstr->UlbActiveDeactive($req);
    }

    public function UlbById(Request $req)
    {
        return $this->Mstr->UlbById($req);
    }

    public function WardList(Request $req)
    {
        return $this->Mstr->WardList($req);
    }

    public function WardAdd(Request $req)
    {
        return $this->Mstr->WardAdd($req);
    }

    public function WardUpdate(Request $req)
    {
        return $this->Mstr->WardUpdate($req);
    }

    public function WardById(Request $req)
    {
        return $this->Mstr->WardById($req);
    }
}
