<?php

namespace App\Traits\Api;

use App\Models\Demand;
use App\Models\ViewUser;
use App\Models\Ward;
use App\Models\Ulb;
use App\Models\TblUserMstr;
use App\Models\TcComplaint;
use App\Models\UserWardPermission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

/**
 * Helpers is a Trait of helpers
 * Created On-
 * Created By-
 * 
 */
trait Helpers
{
    public static $dbname = null;
    static function GenerateDemand($schema, $consumerId, $taxrate, $demandFrom, $userId, $ulbId)
    {

        $demandFrom = strtotime(date('Y-m-d', strtotime($demandFrom)));
        $demandUpto = strtotime(date('Y-m-d'));
        $demand = array();
        while ($demandFrom <= $demandUpto) {

            $payment_from = date('Y-m-d', $demandFrom);
            $payment_to = date('Y-m-t', strtotime($payment_from));
            $demandFrom = strtotime('+1 month', $demandFrom);
            $dmd = new Demand();
            $dmd->setConnection($schema);
            $dmd->consumer_id = $consumerId;
            $dmd->total_tax = $taxrate;
            $dmd->payment_from = $payment_from;
            $dmd->payment_to = $payment_to;
            $dmd->paid_status = 0;
            $dmd->user_id = $userId;
            $dmd->stampdate = date("Y-m-d H:i:s");
            $dmd->demand_date = date('Y-m-d');
            $dmd->is_deactivate = 0;
            $dmd->ulb_id = $ulbId;
            $dmd->save();
            $demand[] = $dmd;
        }
        return $demand;
    }

    static function GetUserDetails($user_id)
    {
        $user = "";
        if ($user_id)
            $user = ViewUser::where('id', $user_id)->first();
        return $user;
    }
    static function GetAllUserDetails()
    {
        $user = "";
        $user = ViewUser::where('status', 1)->get();
        return $user;
    }

    static function GetUserDetailsNew($user_id)
    {
        $user = "";
        $ulbId = 11;
        if (isset($user_id))
            $sql = "SELECT distinct um.name,um.contactno,um.user_type,um.id FROM view_user_mstr um
            left join (select user_id,ulb_id from tbl_user_ward group by user_id,ulb_id) uw on uw.user_id=um.id 
            WHERE um.user_type IN ('Tax Collector', 'Team Leader', 'Jsk') and uw.ulb_id=$ulbId and um.id=$user_id order by name asc";
        else
        $sql = "SELECT distinct um.name,um.contactno,um.user_type,um.id FROM view_user_mstr um
            left join (select user_id,ulb_id from tbl_user_ward group by user_id,ulb_id) uw on uw.user_id=um.id 
            WHERE um.user_type IN ('Tax Collector', 'Team Leader', 'Jsk') and uw.ulb_id=$ulbId order by name asc";
        $user = DB::select($sql);
        return $user;
    }
    static function GetMonthlyFee($dbConn, $responseId, $type, $ulbId)
    {

        $response = array();
        if (isset($responseId) && $type == 'Consumer') {
            $dmd = new Demand($dbConn);
            $dmd = $dmd->where('consumer_id', $responseId)
                ->where('paid_status', 1)
                ->where('is_deactivate', 0)
                ->where('ulb_id', $ulbId)
                ->orderby('id', 'desc')
                ->first();

            $total_tax = ($dmd) ? $dmd->total_tax : '0.00';
            $payUpto = ($dmd) ? date('d-m-Y', strtotime($dmd->payment_to)) : '';
        }

        if (isset($responseId) && $type == 'Apartment') {
            $sql = "SELECT d.consumer_id,max(d.total_tax) as total_tax,max(d.payment_to) as payment_to FROM swm_demands d 
            join swm_consumers c on d.consumer_id=c.id
            where c.apartment_id=" . $responseId . " and d.paid_status=1 and d.is_deactivate=0 and d.ulb_id=" . $ulbId . " group by d.consumer_id";

            $dmds = DB::connection($dbConn)->select($sql);
            $total_tax = 0.00;
            $payUpto = '';
            foreach ($dmds as $d) {

                if ($dmds) {
                    $total_tax += $d->total_tax;
                    $payUpto = date('d-m-Y', strtotime($d->payment_to));
                }
            }
        }

        $response['monthlyFee'] = $total_tax;
        $response['paymentTill'] = $payUpto;
        return $response;
    }

    static function GetDemand($dbConn, $responseId, $type, $ulbId)
    {
        $response = array();
        $total_tax = 0.00;
        $payUpto = '';
        $payFrom = '';
        if (isset($responseId) && $type == 'Consumer') {
            $dmds = new Demand($dbConn);
            $dmds = $dmds->where('consumer_id', $responseId)
                ->where('paid_status', 0)
                ->where('is_deactivate', 0)
                ->where('ulb_id', $ulbId)
                ->orderby('id', 'desc')
                ->get();


            $i = 0;
            foreach ($dmds as $d) {
                $total_tax += $d->total_tax;
                $payUpto = date('d-m-Y', strtotime($d->payment_to));
                if ($i == 0)
                    $payFrom = date('d-m-Y', strtotime($d->payment_from));
                $i++;
            }
        }

        if (isset($responseId) && $type == 'Apartment') {
            $sql = "SELECT d.consumer_id,d.total_tax,d.payment_to,d.payment_from FROM swm_demands d 
            join swm_consumers c on d.consumer_id=c.id
            where c.apartment_id=" . $responseId . " and d.paid_status=0 and d.is_deactivate=0 and d.ulb_id=" . $ulbId . " group by d.consumer_id,d.total_tax,d.payment_to,d.payment_from";

            $dmds = DB::connection($dbConn)->select($sql);

            $i = 0;
            foreach ($dmds as $d) {

                if ($dmds) {
                    $total_tax += $d->total_tax;
                    $payUpto = date('d-m-Y', strtotime($d->payment_to));
                    if ($i == 0)
                        $payFrom = date('d-m-Y', strtotime($d->payment_from));
                    $i++;
                }
            }
        }

        $response['demandAmt'] = $total_tax;
        $response['demandFrom'] = $payFrom;
        $response['demandUpto'] = $payUpto;
        return $response;
    }

    static function GetAllWard($ulb_id, $user_id, $Ward)
    {
        if (isset($user_id)) {
            $sql = "SELECT ward_id FROM tbl_user_ward w
            JOIN tbl_ulb_list u on w.ulb_id=u.id
            WHERE user_id=" . $user_id . " and ulb_id=" . $ulb_id . " and w.stts=1 group by ward_id order by ward_id Asc";

            $ulbs = DB::select($sql);
            $wardarr = array();
            if ($ulbs) {
                foreach ($ulbs as $u) {
                    $getward = $Ward->where('id', $u->ward_id)
                        ->first();
                    if ($getward)
                        $wardarr[] = $getward->name;
                }
            }
            return $wardarr;
        }
    }

    public function GetUlbs($user_id)
    {
        if (isset($user_id)) {
            $sql = "SELECT w.ulb_id,ulb_name,ulb FROM tbl_user_ward w
            JOIN tbl_ulb_list u on w.ulb_id=u.id
            WHERE user_id=" . $user_id . " and w.stts=1 group by w.ulb_id, ulb_name,ulb";

            $ulbs = DB::select($sql);
            $ulbarr = array();
            if ($ulbs) {
                foreach ($ulbs as $u) {
                    $val['ulbId'] = $u->ulb_id;
                    $val['ulbName'] = $u->ulb_name;
                    $val['ulb'] = $u->ulb;
                    $ulbarr[] = $val;
                }
            }
            return $ulbarr;
        }
    }

    public function GetUlbsWithWard($user_id, $Ward)
    {
        if (isset($user_id)) {
            $sql = "SELECT w.ulb_id,ulb_name,ulb FROM tbl_user_ward w
            JOIN tbl_ulb_list u on w.ulb_id=u.id
            WHERE user_id=" . $user_id . " and w.stts=1 group by w.ulb_id, ulb_name,ulb";

            $ulbs = DB::select($sql);
            $ulbarr = array();
            if ($ulbs) {
                foreach ($ulbs as $u) {
                    $val['ulbId'] = $u->ulb_id;
                    $val['ulbName'] = $u->ulb_name;
                    $val['ulb'] = $u->ulb;
                    $val['wards'] = $this->GetAllWard($u->ulb_id, $user_id, $Ward);
                    $ulbarr[] = $val;
                }
            }
            return $ulbarr;
        }
    }




    static function GetSchema($token)
    {
        if (isset($token) && $token <> '') {
            $user = TblUserMstr::where('remember_token', $token)->first();
            if ($user)
                $current_ulb = $user->current_ulb;
            else
                $current_ulb = 11;
            $ulb = Ulb::find($current_ulb);
            // print_r($ulb);
            // //Session::put('ulb', $ulb->db_name);
            return $ulb->db_name;
        }
    }

    static function GetUlbId($userId)
    {
        $default = 11;
        if (isset($userId) && $userId <> '') {
            $userPerm = TblUserMstr::select('current_ulb')
                ->where('id', $userId)
                ->first();
            return $userPerm->current_ulb;
        } else {
            return  $default; // for default for ranchi;
        }
    }

    public function GetUlbData($ulbId)
    {
        $docUrl  = "https://deoghar.smartulb.co.in/swm-deoghar";
        if (isset($ulbId)) {
            $ulb = Ulb::where('id', $ulbId)->first();
            $ulbData = array();
            if ($ulb) {
                $ulbData['ulbName'] = $ulb->ulb_name;
                $ulbData['ulb'] = $ulb->ulb;
                $ulbData['shortName'] = $ulb->short_name;
                $ulbData['contactNo'] = $ulb->contact_no;
                $ulbData['gstNo'] = $ulb->gst_no;
                $ulbData['panNo'] = $ulb->pan_no;
                $ulbData['bankName'] = $ulb->bank_name;
                $ulbData['accountName'] = $ulb->account_name;
                $ulbData['accountNo'] = $ulb->account_no;
                $ulbData['ifscNo'] = $ulb->ifsc_no;
                $ulbData['logo'] =  $docUrl . "/uploads/logo/" . $ulb->logo;
            }
            return $ulbData;
        }
    }

    /**
     * | Response Msg Version2 with apiMetaData
     */
    static function responseMsgs($status, $msg, $data, $apiId = null, $version = null, $queryRunTime = null, $action = null, $deviceId = null)
    {
        return response()->json([
            'status' => $status,
            'msg'   => $msg,
            'data' => $data
        ]);
    }

    /**
     * | Generate Complain No
     */
    function generateComplainNumber($wardNo)
    {
        $ulb     = "11";
        $today   = Carbon::now();
        $month   = $today->format('m');
        $year    = $today->format('Y');
        $wardNo  = str_pad($wardNo, 2, '0', STR_PAD_LEFT);
        $count   = DB::connection('db_swm')->table('swm_tc_complaint')->max('id');
        // $count         = $tcComplain->all()->count();
        $count++;
        $serialNo   = str_pad($count, 5, '0', STR_PAD_LEFT);
        $ComplainNo = $ulb . $wardNo . $year . $month . $serialNo;
        return $ComplainNo;
    }

    /**
     * | To get client ip address
     */
    function getClientIpAddress()
    {
        // Get real visitor IP behind CloudFlare network
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }

        // Sometimes the `HTTP_CLIENT_IP` can be used by proxy servers
        $ip = @$_SERVER['HTTP_CLIENT_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        // Sometimes the `HTTP_X_FORWARDED_FOR` can contain more than IPs 
        $forward_ips = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        if ($forward_ips) {
            $all_ips = explode(',', $forward_ips);

            foreach ($all_ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'];
    }
}
