<?php

namespace App\Repository\Authentications;

use Illuminate\Http\Request;

/**
 * | Created On-07-09-2022 
 * | Created By-Anshu Kumar
 * | The Authentication Interface for Auth Repository
 */
interface iAuth
{
    public function login(Request $request);

    public function CurrentLoginData(Request $req);

    public function ChangePassword(Request $req);

    public function logout(Request $request);

    public function CreateUser(Request $req);

    public function UpdateUser(Request $req);

    public function getAllUser(Request $req);

    public function makeUserActiveDeactive(Request $req);

    public function getUserFormDate(Request $request);

    public function getTcList(Request $req);

    public function ulbSwitch(Request $req);

    public function MenuPermission(Request $req);

    public function MenuPermissionList(Request $request);

    public function UpdateMenuPermission(Request $req);

    public function MenuPermissionByUserType(Request $request);
}
