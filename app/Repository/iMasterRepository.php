<?php

namespace App\Repository;

use Illuminate\Http\Request;

/**
 * | Created On-
 * | Created By-
 * | The Consumer Interface for Consumer Repository
 */
interface iMasterRepository
{
    public function getConsumerFormDate(Request $request);

    public function getApartmentList(Request $request);

    public function getApartmentById(Request $request);

    public function GetConsumerTypeByCategoryId(Request $request);

    public function updateApartment(Request $request);

    public function addApartment(Request $request);

    public function getConsumerCategoryList(Request $request);

    public function ConsumerCategoryAdd(Request $request);

    public function ConsumerCategoryUpdate(Request $request);

    public function ConsumerCategoryById(Request $request);

    public function GetConsumerTypeList(Request $request);

    public function ConsumerTypeAdd(Request $request);

    public function ConsumerTypeUpdate(Request $request);

    public function ConsumerTypeById(Request $request);

    public function UlbList(Request $request);

    public function UlbAdd(Request $request);

    public function UlbUpdate(Request $request);

    public function UlbActiveDeactive(Request $request);

    public function UlbById(Request $request);

    public function WardList(Request $request);

    public function WardAdd(Request $request);

    public function WardUpdate(Request $request);

    public function WardById(Request $request);
}
