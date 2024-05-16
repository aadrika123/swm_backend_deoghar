<?php

namespace App\Repository\Authentications;

use App\Models\TblUserMstr;
use App\Models\UserDetails;
use App\Models\UserType;
use App\Models\UserWardPermission;
use App\Models\Ward;
use App\Models\Ulb;
use App\Models\UserLoginDetail;
use App\Models\MenuMaster;
use App\Models\MenuPermission;
use App\Repository\Authentications\iAuth;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Traits\Api\Helpers;
use Carbon\Carbon;

/**
 * | Created On-07-09-2022 
 * | Created By-Anshu Kumar
 * | Created For- all the Authentication operations like login, Register, ForgetPass etc.
 */
class AuthRepository implements iAuth
{
    use Helpers;

    protected $dbConn;
    protected $Ward;

    public function __construct(Request $request)
    {
        $this->dbConn = $this->GetSchema($request->bearerToken());

        $this->Ward = new Ward($this->dbConn);
    }
    public function login(Request $req)
    {


        try {
            $validator = Validator::make($req->all(), [
                'userName' => 'required',
                'password' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }
            $refUserName = TblUserMstr::where('user_name', $req->userName)
                ->where('status', 1)
                ->first();
            // If the Username is existing
            if ($refUserName) {
                // Checking Password
                $passStmt = $refUserName->user_password == md5($req->password);
                if ($passStmt == true) {
                    $token = $refUserName->createToken('my-app-token')->plainTextToken;
                    $refUserName->remember_token = $token;


                    if ($refUserName->id > 0) {
                        # the code is edited by sam
                        # date : 05/11/2022
                        $getdefault = UserWardPermission::select('ulb_id','tbl_ulb_list.ulb_name')
                            ->join('tbl_ulb_list','tbl_ulb_list.id','=','tbl_user_ward.ulb_id')
                            ->where('user_id', $refUserName->id)
                            ->groupby('ulb_id','ulb_name')
                            ->first();
                        # the end of the edited code by sam
                        if ($getdefault->ulb_id > 0)
                            $refUserName->current_ulb = $getdefault->ulb_id; // Use for set db conncetion dynamic 
                        $refUserName->save();

                        $userlog = new UserLoginDetail();
                        $userlog->user_id = $refUserName->id;
                        $userlog->login_date = Carbon::now()->format("Y-m-d");
                        $userlog->login_time = Carbon::now()->format("h:i:s a");
                        $userlog->ip_address = $req->ip();
                        $userlog->save();
                    }

                    // $response = ['status' => True, 'loginstatus' => 1, 'msg' => 'You Have Logged In', 'data' => $token, 'userId' => $refUserName->id];
                    //changed by talib
                    # edited code by sam
                    $response = ['status' => True, 'loginstatus' => 1, 'msg' => 'You Have Logged In', 'data' => $token, 'userId' => $refUserName->id, 'userTypeId' => $refUserName->user_type_id,'ulbId'=>$getdefault->ulb_id,'ulbName'=>$getdefault->ulb_name];
                    # end of edited code
                    //changed by talib
                    return response($response, 200);
                }
                // If Password Does not Matched
                if ($passStmt == false) {
                    $response = ['status' => True, 'loginstatus' => 0, 'msg' => 'Incorrect Password', 'data' => ''];
                    return response($response, 200);
                }
            }
            // If the UserName is not Existing
            if (!$refUserName) {
                $message = ['status' => True, 'loginstatus' => 0, 'msg' => 'UserName not Found', 'data' => ''];
                return response()->json($message, 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'loginstatus' => 0, 'data' => '', 'msg' => $e], 400);
        }
    }

    public function CurrentLoginData(Request $req)
    {
        try {
            $response = array();
            if ($req->userId) {
                $lastLogin = DB::table('view_user_mstr')
                    ->where('id', $req->userId)
                    ->where('status', 1)
                    ->first();
                $response['id'] = $lastLogin->id;
                $response['userId'] = $lastLogin->user_name;
                $response['userName'] = $lastLogin->name;
                $response['designation'] = $lastLogin->user_type;
                $response['mobileNo'] = $lastLogin->contactno;
                $response['address'] = $lastLogin->address;
                $response['image'] = $lastLogin->photo_path;
                $response['lastVisitedTime'] = $lastLogin->login_time;
                $response['lastVisitedDate'] = date('d-m-Y', $lastLogin->login_date);
                $response['lastIpAddress'] = $lastLogin->ip_address;

                return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function ChangePassword(Request $req)
    {


        try {

            $validator = Validator::make($req->all(), [
                'userName' => 'required',
                'oldPassword' => 'required',
                'newPassword' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            $refUserName = TblUserMstr::where('user_name', $req->userName)
                ->first();
            // If the Username is existing
            if ($refUserName) {
                // Checking Password
                $passStmt = $refUserName->user_password == md5($req->oldPassword);
                if ($passStmt == true) {
                    $refUserName->user_password = md5($req->newPassword);
                    $refUserName->save();

                    $response = ['status' => true, 'msg' => 'Changed password successfully', 'data' => ''];
                    return response($response, 200);
                }
                // If Password Does not Matched
                if ($passStmt == false) {
                    $response = ['status' => true, 'msg' => 'Incorrect old password', 'data' => ''];
                    return response($response, 200);
                }
            }
            // If the UserName is not Existing
            if (!$refUserName) {
                $message = ['status' => true, 'msg' => 'UserName not Found', 'data' => ''];
                return response()->json($message, 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }

    public function logout(Request $request)
    {
        try {
            Auth::logout();
            return response()->json(['status' => True, 'data' => '', 'msg' => 'You are login out'], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }

    public function CreateUser(Request $req)
    {
        # edited code by sam
        $userId = Auth::user()->id;
        $ulbId = $this->GetUlbId($userId);
        # ended 

        try {
            $validator = Validator::make($req->all(), [
                'name' => 'required',
                'contactNo' => 'required',
                'address' => 'required',
                'userType' => 'required',
                'photo' => 'mimes:jpeg,png,jpg,png|max:200',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            $userId = $req->userId;
            $password = md5(12345);
            $date = date('Y-m-d H:i:s');

            $userDtl = new UserDetails();
            $userDtl->name = $req->name;
            $userDtl->contactno = $req->contactNo;
            $userDtl->address = $req->address;
            $userDtl->ulb_id = $ulbId;
            $userDtl->save();

            if ($userDtl->id) {
                $userType = UserType::find($req->userType);
                $fname = explode(" ", $req->name);

                $user = new TblUserMstr();

                $user->user_det_id = $userDtl->id;
                $user->user_type_id = $req->userType;
                $user->user_password = $password;
                $user->ip_address = $req->ip();
                $user->user_id = $userId;
                $user->stampdate = $date;
                $user->status = 1;

                $user->original_pass = 12345;
                $user->save();

                if ($user->id) {
                    $filePath = '';
                    //change by talib
                    //$filePath = 'test_path';
                    //change by talib

                    if (!empty($req->photo)) {
                        $filePath = md5($userDtl->id) . '.' . $req->photo->extension();
                        $req->photo->move(public_path('uploads/user'), $filePath);
                    }

                    $username = $fname[0] . "_" . $userType->short_name . "_" . $user->id;
                    $user->user_name = $username;
                    $user->photo_path = $filePath;
                    $user->save();

                    # edited by sam
                    // if (isset($req->wards) && isset($req->ulbId)) {
                    if (isset($req->wards) && isset($ulbId)) {      //<--------here
                        $wardarray = explode(',', $req->wards);
                        foreach ($wardarray as $key => $value) {
                            $permission  = new UserWardPermission();
                            $permission->user_det_id = $userDtl->id;
                            $permission->user_id = $user->id;
                            // $permission->ulb_id = $req->ulbId;
                            $permission->ulb_id = $ulbId;           //<------------here
                            $permission->ward_id = $value;
                            $permission->save();
                        }
                    }

                    if (isset($ulbId) && !isset($req->wards)) {     //<---------here
                        //foreach ($ulbId as $ulb) 
                        {          //<-----here
                            $permission  = new UserWardPermission();
                            $permission->user_det_id = $userDtl->id;
                            $permission->user_id = $user->id;
                            $permission->ulb_id = $ulbId;           //<----------here
                            $permission->save();
                        }
                    }
                    # ended 
                    return response()->json(['status' => True, 'data' => '', 'msg' => 'User created successfully. user name:' . $username . ' and temporary password:12345'], 200);
                }
            } else {
                return response()->json(['status' => True, 'data' => '', 'msg' => 'Employee created but user not created due to technical issue'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }

    public function UpdateUser(Request $req)
    {
        # edited code by sam
        $ulbuserId = Auth::user()->id;
        $ulbId = $this->GetUlbId($ulbuserId);
        # ended 

        try {
            $validator = Validator::make($req->all(), [
                'userId' => 'required',
                'name' => 'required',
                'contactNo' => 'required',
                'address' => 'required',
                'userType' => 'required',
                'photo' => 'mimes:jpeg,png,jpg,png|max:200',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            $userId = Auth::user()->id;
            $date = date('Y-m-d H:i:s');

            $userDtl = UserDetails::select('tbl_user_details.*')->join('tbl_user_mstr as u', 'u.user_det_id', '=', 'tbl_user_details.id')->where('u.id', $req->userId)->first();
            $userDtl->name = $req->name;
            $userDtl->contactno = $req->contactNo;
            $userDtl->address = $req->address;
            $userDtl->save();

            if ($userDtl->id) {

                // $filePath = '';
                //changed by talib
                $filePath = 'test_path';
                //changed by talib
                if (!empty($req->photo)) {
                    $filePath = md5($userDtl->id) . '.' . $req->photo->extension();
                    $req->photo->move(public_path('uploads/employee'), $filePath);
                }

                $user = TblUserMstr::find($req->userId);
                $user->user_type_id = $req->userType;
                $user->ip_address = $req->ip();
                $user->user_id = $userId;
                $user->stampdate = $date;
                $user->photo_path = $filePath;
                $user->save();

                if ($user->id) {

                    // if (isset($req->wards) && isset($req->ulbId)) {
                    if (isset($req->wards) && isset($ulbId)) {  //<-------here
                        $wardarray = explode(',', $req->wards);
                        UserWardPermission::where('user_id', $req->userId)
                            // ->where('ulb_id', $req->ulbId)
                            ->where('ulb_id', $ulbId)  //<-----------here
                            ->whereNotNull('ward_id')
                            ->update(['stts' => 0]);

                        foreach ($wardarray as $key => $value) {

                            $permission = UserWardPermission::where('user_id', $req->userId)
                                // ->where('ulb_id', $req->ulbId)
                                ->where('ulb_id', $ulbId)  //<--------here
                                ->where('ward_id', $value)
                                ->first();

                            if ($permission) {
                                $permission->stts = 1;
                                $permission->save();
                            } else {

                                $perm  = new UserWardPermission();
                                $perm->user_det_id = $userDtl->id;
                                $perm->user_id = $user->id;
                                // $perm->ulb_id = $req->ulbId;
                                $perm->ulb_id = $ulbId;    //<--------here
                                $perm->ward_id = $value;
                                $perm->save();
                            }
                        }
                    }

                    if (isset($ulbId) && !isset($req->wards)) { //<-------here
                        $ulbarray = explode(',', $ulbId);   //<-----------here
                        UserWardPermission::where('user_id', $req->userId)
                            ->whereNull('ward_id')
                            ->update(['stts' => 0]);

                        foreach ($ulbarray as $key => $value) {
                            $permission = UserWardPermission::where('user_id', $req->userId)
                                ->where('ulb_id', $value)
                                ->whereNull('ward_id')
                                ->first();

                            if ($permission) {
                                $permission->stts = 1;
                                $permission->save();
                            } else {

                                $perm  = new UserWardPermission();
                                $perm->user_det_id = $userDtl->id;
                                $perm->user_id = $user->id;
                                $perm->ulb_id = $value;
                                $perm->save();
                            }
                        }
                    }

                    return response()->json(['status' => True, 'data' => '', 'msg' => 'User updated successfully.'], 200);
                }
            } else {
                return response()->json(['status' => True, 'data' => '', 'msg' => 'Employee not updated'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }


    public function getAllUser(Request $req)
    {
        # edited code by sam
        $ulbId = $this->GetUlbId($req->user()->id);
        # ended
        try {
            $response = array();

            $allUser = DB::table('view_user_mstr')->where('ulb_id', $ulbId);

            if ($req->userId) {
                $allUser = $allUser->where('id', $req->userId);
            }
            $allUser = $allUser->orderBy('id', 'desc')
                ->get();

            foreach ($allUser as $user) {
                $val['userId'] = $user->id;
                $val['userName'] = $user->name;
                $val['designation'] = $user->user_type;
                $val['mobileNo'] = $user->contactno;
                $val['address'] = $user->address;
                $val['image'] = public_path('uploads\user') . $user->photo_path;
                $val['lastVisitedTime'] = $user->login_time;
                $val['lastVisitedDate'] = date('d-m-Y', $user->login_date);
                $val['lastIpAddress'] = $user->ip_address;
                $val['status'] = ($user->status == 1) ? 'Active' : 'Deactive';
                # edited code by sam
                $val['ulbDetails'] = ($req->userId) ? $this->GetUlbsWithWard($req->userId, $this->Ward) : $this->GetUlbs($user->id);
                # ended
                //changed by talib
                $val['userTypeId'] = $user->user_type_id;
                $val['loginUserName'] = $user->user_name;
                //changed by talib

                $response[] = $val;
            }

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }



    public function makeUserActiveDeactive(Request $req)
    {
        try {

            if (isset($req->userId) && isset($req->status)) {
                $user = TblUserMstr::find($req->userId);
                $user->status = $req->status;
                $user->save();

                return response()->json(['status' => True, 'data' => '', 'msg' => 'user updated sucessfully'], 200);
            } else {
                return response()->json(['status' => False, 'data' => '', 'msg' => 'Undefined parameter suppied or lack of information missing'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }

    public function getUserFormDate(Request $request)
    {
        # code updated by sam
        $ulbId = $this->GetUlbId($request->user()->id);
        # ended
        try {

            $responseData = array();
            $responseData['wardList'] = $this->Ward->where('ulb_id', $ulbId)->orderBy('sqorder', 'asc')->get();
            $responseData['ulbList'] = Ulb::get();
            $responseData['userType'] = UserType::get();

            return response()->json(['status' => True, 'data' => $responseData, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }


    public function getTcList(Request $req)
    {
        try {
            $response = array();
            $whereparam = '';

            if (isset($req->ulbId)) {
                $whereparam = ' and uw.ulb_id=' . $req->ulbId;
            }

            $sql = "SELECT distinct name,uw.user_id,contactno,address FROM view_user_mstr um
            left join (select user_id,ulb_id from tbl_user_ward group by user_id,ulb_id) uw on uw.user_id=um.id 
            where user_type='Tax Collector' " . $whereparam. " order by name asc";
            $allUser = DB::select($sql);


            foreach ($allUser as $user) {
                $val['tcId'] = $user->user_id;
                $val['tcName'] = $user->name;
                $val['mobileNo'] = $user->contactno;
                $val['address'] = $user->address;
                $response[] = $val;
            }
            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }


    public function ulbSwitch(Request $req)
    {
        try {

            if (isset($req->ulbId)) {
                $user = TblUserMstr::where('remember_token', $req->bearerToken())->first();
                $response = array();
                if ($user) {
                    $user->current_ulb = $req->ulbId;
                    $user->save();
                    $ulbData = Ulb::where('id', $req->ulbId)->first();
                    $response['id'] = $ulbData->id;
                    $response['ulbName'] = $ulbData->ulb_name;
                    return response()->json(['status' => True, 'data' => $response, 'msg' => 'Ulb switched successfully'], 200);
                } else {
                    return response()->json(['status' => False, 'data' => '', 'msg' => 'User Not found for switching'], 200);
                }
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }

    public function MenuPermission(Request $req)
    {

        try {
            $validator = Validator::make($req->all(), [
                'menuName' => 'required|MIN:5',
                'menuPath' => 'required',
                'underMenu' => 'required|int',
                'menuOrder' => 'required|int',
                'permissionTo' => 'required|array',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            $userId = $req->user()->id;
            $menu = new MenuMaster();
            $menu->menu_name = $req->menuName;
            $menu->file_path = $req->menuPath;
            $menu->under_menu_id = $req->underMenu;
            $menu->menu_order = $req->menuOrder;
            $menu->user_id = $userId;
            $menu->save();

            if ($menu->id) {
                foreach($req->permissionTo as $userType)
                {
                    $permission = new MenuPermission();

                    $permission->menu_id = $menu->id;
                    $permission->user_type_id = $userType;
                    $permission->user_id = $userId;
                    $permission->status = 1;
                    $permission->save();
                }

                return response()->json(['status' => True, 'data' => '', 'msg' => 'Menu created and permission granted'], 200);
            } else {
                return response()->json(['status' => True, 'data' => '', 'msg' => 'Menu created but permission not granted due to technical issue'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }

    public function MenuPermissionList(Request $request)
    {

        try {
            $responseData = array();
            $whereParam = "";
            if(isset($request->menuId))
                $whereParam = "WHERE m.id=".$request->menuId;
            

            $sql = "SELECT m.id,m.menu_name,m.file_path,m1.menu_name as parent_menu,m.menu_order,GROUP_CONCAT(user_type SEPARATOR ', ') as user_type, GROUP_CONCAT(short_name SEPARATOR ', ') as short_name FROM tbl_menu_mtr m
                LEFT JOIN tbl_menu_mtr m1 on m.under_menu_id=m1.id
                JOIN (SELECT user_type,short_name,menu_id FROM tbl_menu_permission p
                    JOIN tbl_user_type ut on p.user_type_id = ut.id
                    WHERE status=1) p on p.menu_id=m.id ". $whereParam ."
                GROUP BY m.id,m.menu_name,m.file_path,m1.menu_name,m.menu_order
                ORDER BY m.menu_order ASC";
            
            $menuList = DB::select($sql);

            foreach ($menuList as $menu) {
                $val['id'] = $menu->id;
                $val['MenuName'] = $menu->menu_name;
                $val['menuPath'] = $menu->file_path;
                $val['underMenu'] = $menu->parent_menu;
                $val['menuOrder'] = $menu->menu_order;
                $val['permissionTo'] = $menu->user_type;
                $responseData[] = $val;
            }

            return response()->json(['status' => True, 'data' => $responseData, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }

    public function UpdateMenuPermission(Request $req)
    {

        try {
            $validator = Validator::make($req->all(), [
                'menuId' => 'required',
                'menuName' => 'required|MIN:5',
                'menuPath' => 'required',
                'underMenu' => 'required|int',
                'menuOrder' => 'required|int',
                'permissionTo' => 'array',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            $userId = $req->user()->id;
            $menu = MenuMaster::find($req->menuId);
            $menu->menu_name = $req->menuName;
            $menu->file_path = $req->menuPath;
            $menu->under_menu_id = $req->underMenu;
            $menu->menu_order = $req->menuOrder;
            $menu->user_id = $userId;
            $menu->save();

            if ($req->menuId && $req->permissionTo) {
                MenuPermission::where('menu_id', $req->menuId)
                                ->where('status', 1)
                                ->update(['status' => 0]);
                foreach($req->permissionTo as $userType)
                {
                    $permission = new MenuPermission();

                    $permission->menu_id = $menu->id;
                    $permission->user_type_id = $userType;
                    $permission->user_id = $userId;
                    $permission->status = 1;
                    $permission->save();
                }

                return response()->json(['status' => True, 'data' => '', 'msg' => 'Menu updated and permission granted'], 200);
            } else {
                return response()->json(['status' => True, 'data' => '', 'msg' => 'Menu updated successfully'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }

    public function MenuPermissionByUserType(Request $request)
    {

        try {
            $responseData = array();

            if(isset($request->userType))
            {
                $sql = "SELECT m.id,m.menu_name,m.file_path,m.menu_order FROM tbl_menu_mtr m
                    JOIN tbl_menu_permission p on p.menu_id=m.id 
                    WHERE p.status=1 and (m.under_menu_id is null or m.under_menu_id = 0) AND p.user_type_id=". $request->userType ."
                    ORDER BY m.menu_order ASC";
                
                $menuList = DB::select($sql);

                foreach ($menuList as $menu) {

                    $childMenus = MenuMaster::where('under_menu_id', $menu->id)
                                            ->orderBy('menu_order', 'ASC')
                                            ->get();
                    $responseChild = array();
                    foreach($childMenus as $childMenu)
                    {
                        $val['id'] = $childMenu->id;
                        $val['MenuName'] = $childMenu->menu_name;
                        $val['menuPath'] = $childMenu->file_path;
                        $val['menuOrder'] = $childMenu->menu_order;
                        $responseChild[] = $val;
                    }

                    $val['id'] = $menu->id;
                    $val['MenuName'] = $menu->menu_name;
                    $val['menuPath'] = $menu->file_path;
                    $val['underMenu'] = $responseChild;
                    $val['menuOrder'] = $menu->menu_order;
                    $responseData[] = $val;
                }

                return response()->json(['status' => True, 'data' => $responseData, 'msg' => ''], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e], 400);
        }
    }
}