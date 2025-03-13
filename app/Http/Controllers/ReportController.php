<?php

namespace App\Http\Controllers;

use App\Models\TblTcTracking;
use Illuminate\Http\Request;
use App\Repository\iReportRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use App\Traits\Api\Helpers;

class ReportController extends Controller
{
    use Helpers;
    protected $report;

    public function __construct(iReportRepository $report)
    {
        $this->rep = $report;
    }

    public function GetReportData(Request $request)
    {
        return $this->rep->ReportData($request);
    }

    public function consumerEditLogDetails(Request $request)
    {
        return $this->rep->consumerEditLogDetails($request);
    }
    

    public function monthlyComparison(Request $request)
    {
        return $this->rep->monthlyComparison($request);
    }

    public function GetDemandReceiptData(Request $request)
    {
        return $this->rep->DemandReceipt($request);
    }
    /**
     * | Add TC Geo Location
     */
    public function addTcGeoLocation(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                "latitude"    => "required",
                "longitude"   => "required",
            ]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {

            $user           = auth()->user();
            $mTblTcTracking = new TblTcTracking();

            $metaReqs = [
                'user_id'   => $user->id,
                'ulb_id'    => $user->current_ulb,
                'latitude'  => $req->latitude,
                'longitude' => $req->longitude,
            ];

            $mTblTcTracking->createGeoLocation($metaReqs);

            return $this->responseMsgs(true, "Tc Geolocation Added Succesfully", "");
        } catch (Exception $e) {
            return $this->responseMsgs(true,  $e->getMessage(), "");
        }
    }

    /**
     * | Tc Geolocation List
     */
    public function tcGeolocationList(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                "fromDate"   => "nullable",
                "toDate"     => "nullable",
            ]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {
            $perPage = $req->perPage ?? 10;
            $authUser = auth()->user();
            $fromDate = $req->fromDate ?? Carbon::now()->format('Y-m-d');
            $toDate   = $req->toDate   ?? Carbon::now()->format('Y-m-d');
            $mTblTcTracking = new TblTcTracking();

            $logDetail = $mTblTcTracking->listgeoLocation()
                ->whereBetween('created_at', [$fromDate . ' 00:00:01', $toDate . ' 23:59:59'])
                ->where('tbl_tc_trackings.status', true)
                ->where('tbl_tc_trackings.ulb_id', $authUser->current_ulb);

            if (isset($req->tcId))
                $logDetail = $logDetail->where('user_id', $req->tcId);

            $logDetail = $logDetail
                ->paginate($perPage);

            return $this->responseMsgs(true, "Tc Geolocation List", $logDetail);
        } catch (Exception $e) {
            return $this->responseMsgs(true,  $e->getMessage(), "");
        }
    }

    /**
     * | Get TC Geo Location
     */
    public function getTcGeolocation(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                "id"    => "required",
            ]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {
            $mTblTcTracking = new TblTcTracking();
            $data =  $mTblTcTracking->listgeoLocation()
                ->where('status', true)
                ->where('tbl_tc_trackings.id', $req->id)
                ->first();

            return $this->responseMsgs(true, "Tc Geolocation Data", $data);
        } catch (Exception $e) {
            return $this->responseMsgs(true,  $e->getMessage(), "");
        }
    }
    // public function generateNextMonthDemand()
    // {
    //     Artisan::call('demand:generate-next-month');
    //     return response()->json(['message' => 'Next month’s demands generation triggered successfully!']);
    // }
}
