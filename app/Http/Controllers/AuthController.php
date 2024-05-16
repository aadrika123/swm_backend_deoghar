<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repository\Authentications\AuthRepository;

class AuthController extends Controller
{
    /**
     * | Created On-07-09-2022 
     * | Created By-Anshu Kumar
     * | Controller For the User Login and Register Credentials Operations
     */

    //  Initializing construct function for Repositoy
    protected $eloquentAuth;
    public function __construct(AuthRepository $eloquentAuth)
    {
        $this->Repository = $eloquentAuth;
    }

    // User Login
    public function login(Request $req)
    {
        return $this->Repository->login($req);
    }

    public function GetHomePageData(Request $req)
    {
        return $this->Repository->CurrentLoginData($req);
    }

    public function ChangePassword(Request $req)
    {
        return $this->Repository->ChangePassword($req);
    }

    public function Logout(Request $req)
    {
        return $this->Repository->logout($req);
    }

    public function CreateUser(Request $req)
    {
        return $this->Repository->CreateUser($req);
    }

    public function UpdateUser(Request $req)
    {
        return $this->Repository->UpdateUser($req);
    }

    public function getAllUser(Request $req)
    {
        return $this->Repository->getAllUser($req);
    }

    public function userActiveDeactive(Request $req)
    {
        return $this->Repository->makeUserActiveDeactive($req);
    }

    public function getUserFormDate(Request $req)
    {
        return $this->Repository->getUserFormDate($req);
    }

    public function getTcList(Request $req)
    {
        return $this->Repository->getTcList($req);
    }

    public function ulbSwitch(Request $req)
    {
        return $this->Repository->ulbSwitch($req);
    }

    public function MenuPermission(Request $req)
    {
        return $this->Repository->MenuPermission($req);
    }

    public function MenuPermissionList(Request $req)
    {
        return $this->Repository->MenuPermissionList($req);
    }

    public function UpdateMenuPermission(Request $req)
    {
        return $this->Repository->UpdateMenuPermission($req);
    }

    public function MenuPermissionByUserType(Request $req)
    {
        return $this->Repository->MenuPermissionByUserType($req);
    }

    
    
    
    
}
