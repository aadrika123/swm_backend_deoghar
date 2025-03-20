<?php

namespace App\Repository;

use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Consumer;
use App\Models\ConsumerDeactivateDeatils;
use App\Models\Transaction;
use App\Models\Collections;
use App\Models\ConsumerEditLog;
use App\Models\Demand;
use App\Models\TransactionDeactivate;
use App\Models\TransactionModeChange;
use App\Models\TransactionVerification;
use App\Models\UserLoginDetail;
use App\Models\PaymentDeny;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Traits\Api\Helpers;
use PhpOption\None;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * | Created On-09-24-2022 
 * | Created By-
 * | Created For- Report related api 
 */
class ReportRepository implements iReportRepository
{
    use Helpers;

    protected $dbConn;
    protected $Consumer;
    protected $ConsumerDeactivateDeatils;
    protected $Transaction;
    protected $Collections;
    protected $TransactionDetails;
    protected $TransactionDeactivate;
    protected $TransactionVerification;
    protected $PaymentDeny;
    protected $TransactionModeChange;
    protected $mConsumerEditLog;
    protected $mDemand;

    public function __construct(Request $request)
    {
        $this->dbConn = $this->GetSchema($request->bearerToken());
        $this->Consumer = new Consumer($this->dbConn);
        $this->ConsumerDeactivateDeatils = new ConsumerDeactivateDeatils($this->dbConn);
        $this->Transaction = new Transaction($this->dbConn);
        $this->TransactionDeactivate = new TransactionDeactivate($this->dbConn);
        $this->TransactionVerification = new TransactionVerification($this->dbConn);
        $this->Collections = new Collections($this->dbConn);
        $this->PaymentDeny = new PaymentDeny($this->dbConn);
        $this->TransactionModeChange = new TransactionModeChange($this->dbConn);
        $this->mConsumerEditLog = new ConsumerEditLog($this->dbConn);
        $this->mDemand = new Demand($this->dbConn);
    }
    #Arshad  
    #modified by prity pandey 08-08-24
    public function ReportData(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            $response = array();
            $category = $request->consumerCategory;
            if (isset($request->fromDate) && isset($request->toDate) && isset($request->reportType)) {
                $response = array();
                // if ($request->reportType == 'dailyCollection')
                //     $response = $this->DailyCollection($request->fromDate, $request->toDate, $request->wardNo, $request->consumerCategory, $request->consumerType, $request->apartmentId, $request->mode);

                //changed by talib
                if ($request->reportType == 'dailyCollection')
                    $response = $this->DailyCollection($request->fromDate, $request->toDate, $request->tcId, $request->wardNo, $request->consumerCategory, $request->consumerType, $request->apartmentId, $request->mode, $ulbId, $request);

                if ($request->reportType == 'tcCollection')
                    $response = $this->TcCollection($request->fromDate, $request->toDate, $request->tcId, $request->wardNo, $request->consumerCategory, $request->consumerType, $request->apartmentId, $request->mode, $ulbId);
                // changed by talib

                if ($request->reportType == 'conAdd')
                    $response = $this->ConsumerAdd($request->fromDate, $request->toDate, $ulbId, $request->wardNo, $request->consumerCategory, $request->tcId, $request->consumerType, $request);

                if ($request->reportType == 'conDect')
                    $response = $this->ConsumerDect($request->fromDate, $request->toDate, $ulbId, $request->wardNo, $request->consumerCategory, $request->tcId, $request->consumerType, $request);

                if ($request->reportType == 'tranDect')

                    $response = $this->TransactionDeactivate($request->fromDate, $request->toDate, $request->tcId, $ulbId, $request->wardNo, $request->consumerCategory, $request->mode, $request->consumerType, $request);

                if ($request->reportType == 'cashVeri')
                    $response = $this->CashVerification($request->fromDate, $request->toDate, $request->tcId, $ulbId, $request->wardNo, $request->consumerCategory,  $request->mode, $request->consumerType, $request);

                if ($request->reportType == 'bankRec')
                    $response = $this->BankReconcilliation($request->fromDate, $request->toDate, $request->tcId, $ulbId, $request, $request->consumerCategory, $request->consumerType, $request->mode, $request->page, $request->perPage);

                if ($request->reportType == 'tcDaily')
                    $response = $this->TcDailyActivity($request->fromDate, $request->toDate, $request->tcId, $ulbId, $request->consumerCategory, $request);

                if ($request->reportType == 'tranModeChange')
                    $response = $this->TransactionModeChange($request->fromDate, $request->toDate, $request->tcId, $ulbId, $request->wardNo, $request->consumerCategory, $request);

                if ($request->reportType == 'consumereditlog')
                    $response = $this->consumerEditLog($request->fromDate, $request->toDate, $request->tcId, $ulbId, $request->wardNo, $request->consumerCategory, $request->consumerType, $request);

                // if ($request->reportType == 'monthlyComparison')
                //     $response = $this->monthlyComparison($request->fromMonth, $request->wardNo, $request->consumerCategory, $request->tcId);

                return response()->json(['status' => True, 'data' =>  $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function DailyCollection($From, $Upto, $tcId = null, $wardNo = null, $consumerCategory = null, $consumertype = null, $apartmentId = null, $mode = null, $ulbId, Request $request)
    {
        // Default Pagination Size (50 per page)
        $perPage = $request->perPage ?? 50;
        // Convert Dates to Proper Format
        $From = Carbon::parse($From)->format('Y-m-d');
        $Upto = Carbon::parse($Upto)->format('Y-m-d');
        //  Use Query Builder Instead of Raw SQL
        $query = $this->Transaction->select(
            'swm_transactions.*',
            'swm_consumers.ward_no',
            'swm_consumers.consumer_no',
            'swm_consumers.name',
            'a.apt_code',
            'a.apt_name'
        )
            ->leftJoin('swm_consumers', 'swm_transactions.consumer_id', '=', 'swm_consumers.id')
            ->leftJoin('swm_apartments as a', 'swm_transactions.apartment_id', '=', 'a.id')
            ->leftJoin('swm_transaction_deactivates as td', 'td.transaction_id', '=', 'swm_transactions.id')
            ->whereBetween('swm_transactions.transaction_date', [$From, $Upto])
            ->where('swm_transactions.ulb_id', $ulbId)
            ->whereNotIn('swm_transactions.paid_status', [0, 3])
            ->whereNull('td.id');

        //  Apply Filters Dynamically
        if ($tcId) $query->where('swm_transactions.user_id', $tcId);
        if ($wardNo) $query->where('swm_consumers.ward_no', $wardNo);
        if ($consumerCategory) $query->where('swm_consumers.consumer_category_id', $consumerCategory);
        if ($consumertype) $query->where('swm_consumers.consumer_type_id', $consumertype);
        if ($apartmentId) $query->where('swm_transactions.apartment_id', $apartmentId);
        if ($mode) $query->where('swm_transactions.payment_mode', $mode);

        $allAmount = $query->sum('total_payable_amt');
        $allDemand = $query->sum('total_demand_amt');
        $allPending = $query->sum('total_remaining_amt');
        $allCash = (clone $query)->where('payment_mode', 'Cash')->sum('total_payable_amt');
        $allCheque = (clone $query)->where('payment_mode', 'Cheque')->sum('total_payable_amt');
        $alldd = (clone $query)->where('payment_mode', 'DD')->sum('total_payable_amt');
        //  Paginate the Query (Default: 50 records per page)
        $allTrans = $query->orderBy('swm_transactions.transaction_date', 'DESC')->paginate($perPage);

        //  Transform Data Efficiently Using `map()`
        $transactions = $allTrans->map(function ($trans) {
            $firstRecord = $this->Collections->where('transaction_id', $trans->id)->orderBy('id', 'asc')->first();
            $lastRecord = $this->Collections->where('transaction_id', $trans->id)->orderBy('id', 'desc')->first();
            $userData = $this->GetUserDetails($trans->user_id);
            return [
                'tcName' => $userData->name ?? "",
                'mobileNo' => $userData->contactno ?? "",
                'designation' => $userData->user_type ?? "",
                'wardNo' => $trans->ward_no,
                'consumerNo' => $trans->consumer_no,
                'consumerName' => $trans->name,
                'apartmentId' => $trans->apartment_id,
                'consumerId' => $trans->consumer_id,
                'apartmentCode' => $trans->apt_code,
                'apartmentName' => $trans->apt_name,
                'transactionNo' => (string)$trans->transaction_no,
                'transactionMode' => $trans->payment_mode,
                'transactionDate' => Carbon::parse($trans->transaction_date)->format('d-m-Y'),
                'transactionTime' => Carbon::parse($trans->stampdate)->format('h:i A'),
                'consumerCategory' => $trans->name,
                'amount' => $trans->total_payable_amt,
                'demandFrom' => $firstRecord ? Carbon::parse($firstRecord->payment_from)->format('d-m-Y') : '',
                'demandUpto' => $lastRecord ? Carbon::parse($lastRecord->payment_to)->format('d-m-Y') : '',
            ];
        });

        //  Calculate Totals More Efficiently
        $totCollection = $allTrans->sum('total_payable_amt');
        $totDemand = $allTrans->sum('total_demand_amt');
        $totPending = $allTrans->sum('total_remaining_amt');
        $totCash = $allTrans->where('payment_mode', 'Cash')->sum('total_payable_amt');
        $totCheque = $allTrans->where('payment_mode', 'Cheque')->sum('total_payable_amt');
        $totdd = $allTrans->where('payment_mode', 'DD')->sum('total_payable_amt');
        $list = [
            'data' => $transactions,
            'allCollection' => $allAmount,
            'allDemand' => $allDemand,
            'allPending' => $allPending,
            'allCash' => $allCash,
            'allCheque' => $allCheque,
            'allDD' => $alldd,
            'totalCollection' => $totCollection,
            'totalDemand' => $totDemand,
            'totalPending' => $totPending,
            'totalCash' => $totCash,
            'totalCheque' => $totCheque,
            'totalDD' => $totdd,
            'total' => $allTrans->total(),
            'per_page' => $allTrans->perPage(),
            'current_page' => $allTrans->currentPage(),
            'last_page' => $allTrans->lastPage(),
        ];

        //  Return Paginated Response
        return $list;
    }

    public function DailyCollection_old($From, $Upto, $tcId = null, $wardNo = null, $consumerCategory = null, $consumertype = null, $apartmentId = null, $mode = null, $ulbId, $request)
    {
        $perPage = $request->perPage ? $request->perPage : 10;
        $page = $request->page && $request->page > 0 ? $request->page : 1;
        $limit = $perPage;
        $offset =  $request->page && $request->page > 0 ? ($request->page * $perPage) : 0;

        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');
        $sql = "SELECT swm_transactions.id,swm_transactions.user_id,swm_transactions.apartment_id,swm_transactions.consumer_id,swm_transactions.transaction_no,swm_transactions.payment_mode,swm_transactions.transaction_date,swm_transactions.stampdate,swm_transactions.total_payable_amt,swm_transactions.total_demand_amt,swm_transactions.total_remaining_amt,cons.ward_no,cons.consumer_no,cons.name,aprts.apt_code, aprts.apt_name FROM swm_transactions LEFT JOIN swm_consumers AS cons ON swm_transactions.consumer_id = cons.id LEFT JOIN swm_apartments AS aprts ON swm_transactions.apartment_id = aprts.id LEFT JOIN swm_transaction_deactivates AS td ON swm_transactions.id = td.transaction_id WHERE swm_transactions.transaction_date >= '$From' AND swm_transactions.transaction_date <= '$Upto' AND swm_transactions.ulb_id = $ulbId AND swm_transactions.paid_status NOT IN (0, 3) AND td.id IS NULL";
        if (isset($tcId))
            $sql .= " AND swm_transactions.user_id='$tcId'";
        if (isset($wardNo))
            $sql .= " AND cons.ward_no='$wardNo'";

        if (isset($consumerCategory))
            $sql .= " AND cons.consumer_category_id='$consumerCategory'";

        if (isset($consumertype))
            $sql .= " AND cons.consumer_type_id='$consumertype'";

        if (isset($apartmentId))
            $sql .= " AND cons.apartment_id='$apartmentId'";

        if (isset($mode))
            $sql .= " AND swm_transactions.payment_mode='$mode'";

        $sql .= " ORDER BY swm_transactions.transaction_date DESC";
        // return $sql;die;
        $allTrans = DB::connection($this->dbConn)->select($sql);

        return   $data = DB::TABLE(DB::connection($this->dbConn)->RAW("($sql )AS prop"))->get();

        $total = (collect(DB::connection($this->dbConn)->SELECT($allTrans))->first())->total ?? 0;
        $lastPage = ceil($total / $perPage);
        return  $list = [
            "current_page" => $page,
            "data" => $data,
            "total" => $total,
            "per_page" => $perPage,
            "last_page" => $lastPage
        ];


        $totCollection = 0;
        $totDemand = 0;
        $totPending = 0;
        $totCash = 0;
        $totCheque = 0;
        $totdd = 0;
        $transaction = array();
        foreach ($allTrans as $trans) {
            //$collection = $this->Collections->where('transaction_id', $trans->id);
            $firstrecord = $this->Collections->where('transaction_id', $trans->id)->orderBy('id', 'asc')->first();
            $lastrecord = $this->Collections->where('transaction_id', $trans->id)->orderBy('id', 'desc')->first();
            $getuserdata = $this->GetUserDetails($trans->user_id);
            $val['tcName'] = $getuserdata->name ?? "";
            $val['mobileNo'] = $getuserdata->contactno ?? "";
            $val['designation'] = $getuserdata->user_type ?? "";
            $val['wardNo'] = $trans->ward_no;
            $val['consumerNo'] = $trans->consumer_no;
            $val['consumerName'] = $trans->name;
            $val['apartmentId'] = $trans->apartment_id;
            $val['consumerId'] = $trans->consumer_id;
            $val['apartmentCode'] = $trans->apt_code;
            $val['apartmentName'] = $trans->apt_name;
            $val['transactionNo'] = (string)$trans->transaction_no;
            $val['transactionMode'] = $trans->payment_mode;
            $val['transactionDate'] = Carbon::create($trans->transaction_date)->format('d-m-Y');
            $val['transactionTime'] = Carbon::create($trans->stampdate)->format('h:i A');
            $val['consumerCategory'] = $trans->name;
            $val['amount'] = $trans->total_payable_amt;
            $val['demandFrom'] = ($firstrecord) ? Carbon::create($firstrecord->payment_from)->format('d-m-Y') : '';
            $val['demandUpto'] = ($lastrecord) ? Carbon::create($lastrecord->payment_to)->format('d-m-Y') : '';
            $transaction[] = $val;

            $totCollection += $trans->total_payable_amt;
            $totDemand += $trans->total_demand_amt;
            $totPending += $trans->total_remaining_amt;


            if ($trans->payment_mode == 'Cash')
                $totCash += $trans->total_payable_amt;

            if ($trans->payment_mode == 'Cheque')
                $totCheque += $trans->total_payable_amt;

            if ($trans->payment_mode == 'DD')
                $totdd += $trans->total_payable_amt;
        }

        $response['transactions'] = $transaction;
        $response['totalCollection'] = $totCollection;
        $response['totalDemand'] = $totDemand;
        $response['totalPending'] = $totPending;
        $response['totalCash'] = $totCash;
        $response['totalCheque'] = $totCheque;
        $response['totalDD'] = $totdd;


        $response['totalDD'] = $totdd;


        // $items = $paginator->items();
        // $total = $paginator->total();
        // $numberOfPages = ceil($total / $perPage);
        $list = [
            "current_page" => $paginator->currentPage(),
            "last_page" => $paginator->lastPage(),
            "totalHolding" => $totalHolding,
            "totalAmount" => $totalAmount,
            "data" => $paginator->items(),
            "total" => $paginator->total(),
            // "numberOfPages" => $numberOfPages
        ];
        return $response;
    }

    public function TcCollection($From, $Upto, $tcId = null, $wardNo = null, $consumerCategory = null, $consumertype = null, $apartmentId = null, $mode = null, $ulbId)
    {

        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');

        $allTrans = $this->Transaction->select('swm_transactions.*', 'swm_consumers.ward_no', 'consumer_no', 'name', 'a.apt_code', 'a.apt_name')
            ->leftjoin('swm_consumers', 'swm_transactions.consumer_id', '=', 'swm_consumers.id')
            ->leftjoin('swm_apartments as a', 'swm_transactions.apartment_id', '=', 'a.id')
            ->leftjoin('swm_transaction_deactivates as td', 'td.transaction_id', '=', 'swm_transactions.id')
            ->leftJoin('tbl_user_ward as uw', 'uw.user_id', '=', 'swm_transactions.user_id')
            ->leftJoin('view_user_mstr as um', function ($join) {
                $join->on('um.id', '=', 'uw.user_id')
                    ->where('um.user_type', 'Tax Collector');
            })
            ->whereBetween('transaction_date', [$From, $Upto])
            ->where('swm_transactions.ulb_id', $ulbId)
            ->whereNotIn('swm_transactions.paid_status', [0, 3])
            ->whereNull('td.id');

        //changed by talib
        if (isset($tcId))
            $allTrans = $allTrans->where('swm_transactions.user_id', $tcId);
        //changed by talib   
        if (isset($wardNo))
            $allTrans = $allTrans->where('swm_consumers.ward_no', $wardNo);

        if (isset($consumerCategory))
            $allTrans = $allTrans->where('swm_consumers.consumer_category_id', $consumerCategory);

        if (isset($consumertype))
            $allTrans = $allTrans->where('swm_consumers.consumer_type_id', $consumertype);

        if (isset($apartmentId))
            $allTrans = $allTrans->where('swm_consumers.apartment_id', $apartmentId);

        if (isset($mode))
            $allTrans = $allTrans->where('swm_transactions.payment_mode', $mode);

        $allTrans = $allTrans->orderBy('transaction_date', 'DESC')->get();

        $totCollection = 0;
        $totDemand = 0;
        $totPending = 0;
        $totCash = 0;
        $totCheque = 0;
        $totdd = 0;
        $transaction = array();
        foreach ($allTrans as $trans) {
            //$collection = $this->Collections->where('transaction_id', $trans->id);
            $firstrecord = $this->Collections->where('transaction_id', $trans->id)->orderBy('id', 'asc')->first();
            $lastrecord = $this->Collections->where('transaction_id', $trans->id)->orderBy('id', 'desc')->first();
            $getuserdata = $this->GetUserDetails($trans->user_id);
            $val['tcName'] = $getuserdata->name ?? "";
            $val['mobileNo'] = $getuserdata->contactno ?? "";
            $val['designation'] = $getuserdata->user_type ?? "";
            $val['wardNo'] = $trans->ward_no;
            $val['consumerNo'] = $trans->consumer_no;
            $val['consumerName'] = $trans->name;
            $val['apartmentId'] = $trans->apartment_id;
            $val['consumerId'] = $trans->consumer_id;
            $val['apartmentCode'] = $trans->apt_code;
            $val['apartmentName'] = $trans->apt_name;
            $val['transactionNo'] = (string)$trans->transaction_no;
            $val['transactionMode'] = $trans->payment_mode;
            $val['transactionDate'] = Carbon::create($trans->transaction_date)->format('d-m-Y');
            $val['transactionTime'] = Carbon::create($trans->stampdate)->format('h:i A');
            $val['amount'] = $trans->total_payable_amt;
            $val['demandFrom'] = ($firstrecord) ? Carbon::create($firstrecord->payment_from)->format('d-m-Y') : '';
            $val['demandUpto'] = ($lastrecord) ? Carbon::create($lastrecord->payment_to)->format('d-m-Y') : '';
            $transaction[] = $val;

            $totCollection += $trans->total_payable_amt;
            $totDemand += $trans->total_demand_amt;
            $totPending += $trans->total_remaining_amt;


            if ($trans->payment_mode == 'Cash')
                $totCash += $trans->total_payable_amt;

            if ($trans->payment_mode == 'Cheque')
                $totCheque += $trans->total_payable_amt;

            if ($trans->payment_mode == 'DD')
                $totdd += $trans->total_payable_amt;
        }

        $response['transactions'] = $transaction;
        $response['totalCollection'] = $totCollection;
        $response['totalDemand'] = $totDemand;
        $response['totalPending'] = $totPending;
        $response['totalCash'] = $totCash;
        $response['totalCheque'] = $totCheque;
        $response['totalDD'] = $totdd;

        return $response;
    }

    public function ConsumerAdd($From, $Upto, $ulbId, $wardNo, $consumerCategory, $tcId, $consumerType, Request $request)
    {
        // Default Pagination Size (50 per page)
        $perPage = $request->perPage ?? 50;
        $response = array();
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');


        $consumers = $this->Consumer;
        $consumers = $consumers->latest('id')
            ->where('is_deactivate', 0)
            ->where('ulb_id', $ulbId)
            ->whereBetween('entry_date', [$From, $Upto]);
        if (isset($wardNo)) {
            $consumers = $consumers->where('ward_no', $wardNo);
        }
        if (isset($consumerCategory)) {
            $consumers = $consumers->where('consumer_category_id', $consumerCategory);
        }

        if (isset($consumerType)) {
            $consumers = $consumers->where('consumer_type_id', $consumerType);
        }

        if (isset($tcId)) {
            $consumers = $consumers->where('user_id', $tcId);
        }
        $consumers = $consumers->paginate($perPage);
        $consumerDetails = $consumers->map(function ($consumer) {
            $user = $this->GetUserDetails($consumer->user_id);
            return [
                'entryDate' => Carbon::create($consumer->entry_date)->format('d-m-Y'),
                'consumerNo' => $consumer->consumer_no,
                'wardNo' => $consumer->ward_no,
                'consumerName' => $consumer->name,
                'consumerMobile' => $consumer->mobile_no,
                'entryBy' => ($user) ? $user->name : "",
            ];
        });
        $response = [
            'data' => $consumerDetails,
            'current_page' => $consumers->currentPage(),
            'total' => $consumers->total(),
            'per_page' => $consumers->perPage(),
            'last_page' => $consumers->lastPage(),
            'next_page_url' => $consumers->nextPageUrl(),
            'prev_page_url' => $consumers->previousPageUrl(),
        ];
        return $response;
    }
    public function ConsumerAddOld($From, $Upto, $ulbId, $wardNo, $consumerCategory, $tcId, $consumerType)
    {
        $response = array();
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');


        $consumers = $this->Consumer;
        $consumers = $consumers->latest('id')
            ->where('is_deactivate', 0)
            ->where('ulb_id', $ulbId)
            ->whereBetween('entry_date', [$From, $Upto])
            ->paginate(1000);
        if (isset($wardNo)) {
            $consumers = $consumers->where('ward_no', $wardNo);
        }
        if (isset($consumerCategory)) {
            $consumers = $consumers->where('consumer_category_id', $consumerCategory);
        }
        if (isset($tcId)) {
            $consumers = $consumers->where('user_id', $tcId);
        }

        foreach ($consumers as $consumer) {
            $user = $this->GetUserDetails($consumer->user_id);
            $val['entryDate'] = Carbon::create($consumer->entry_date)->format('d-m-Y');
            $val['consumerNo'] = $consumer->consumer_no;
            $val['wardNo'] = $consumer->ward_no;
            $val['consumerName'] = $consumer->name;
            $val['consumerMobile'] = $consumer->mobile_no;
            $val['entryBy'] = ($user) ? $user->name : "";
            $response[] = $val;
        }
        return $response;
    }

    // public function ConsumerDect($From, $Upto, $ulbId, $wardNo, $consumerCategory, $tcId)
    // {
    //     $response = array();
    //     $From = Carbon::create($From)->format('Y-m-d');
    //     $Upto = Carbon::create($Upto)->format('Y-m-d');

    //     $consumers = $this->ConsumerDeactivateDeatils->latest('id')
    //         ->select('swm_consumer_deactivates.*', 'name', 'consumer_no', 'mobile_no', 'swm_consumers.ward_no')
    //         ->join('swm_consumers', 'swm_consumer_deactivates.consumer_id', '=', 'swm_consumers.id')
    //         ->where('swm_consumer_deactivates.ulb_id', $ulbId)
    //         ->whereBetween('deactivation_date', [$From, $Upto])
    //         ->orderBy('swm_consumer_deactivates.id', 'desc')
    //         ->paginate(1000);

    //     if (isset($wardNo)) {
    //         $consumers = $consumers->where('ward_no', $wardNo);
    //     }
    //     if (isset($consumerCategory)) {
    //         $consumers = $consumers->where('consumer_category_id', $consumerCategory);
    //     }
    //     if (isset($tcId)) {
    //         $consumers = $consumers->where('user_id', $tcId);
    //     }

    //     foreach ($consumers as $consumer) {
    //         $user = $this->GetUserDetails($consumer->deactivated_by);
    //         $val['deactivateDate'] = Carbon::create($consumer->deactivation_date)->format('d-m-Y');
    //         $val['consumerNo'] = $consumer->consumer_no;
    //         $val['consumerName'] = $consumer->name;
    //         $val['wardNo'] = $consumer->ward_no;
    //         $val['consumerMobile'] = $consumer->mobile_no;
    //         $val['deactivateBy'] = ($user) ? $user->name : "";
    //         $val['remarks'] = $consumer->remarks;
    //         $response[] = $val;
    //     }
    //     return $response;
    // }

    public function ConsumerDect($From, $Upto, $ulbId, $wardNo = null, $consumerCategory = null, $tcId = null, $consumerType = null, Request $request)
    {
        $response = [];

        // Format date inputs
        $From = Carbon::parse($From)->format('Y-m-d');
        $Upto = Carbon::parse($Upto)->format('Y-m-d');
        $perPage = $request->perPage ?? 50;
        // Build initial query
        $query = $this->ConsumerDeactivateDeatils->latest('id')
            ->select(
                'swm_consumer_deactivates.*',
                'swm_consumers.name',
                'consumer_no',
                'mobile_no',
                'swm_consumers.ward_no'
            )
            ->join('swm_consumers', 'swm_consumer_deactivates.consumer_id', '=', 'swm_consumers.id')
            ->where('swm_consumer_deactivates.ulb_id', $ulbId)
            ->whereBetween('deactivation_date', [$From, $Upto])
            ->orderBy('swm_consumer_deactivates.id', 'desc');

        // Apply filters
        if (!empty($tcId)) {
            $query->where('swm_consumer_deactivates.deactivated_by', $tcId);
        }
        if (!empty($wardNo)) {
            $query->where('swm_consumers.ward_no', $wardNo);
        }
        if (!empty($consumerCategory)) {
            $query->where('swm_consumers.consumer_category_id', $consumerCategory);
        }
        if (!empty($consumerType)) {
            $query->where('swm_consumers.consumer_type_id', $consumerType);
        }

        // Paginate results
        $consumers = $query->paginate($perPage);
        $DeactConsumers = $consumers->map(function ($consumer) {
            $user = $this->GetUserDetails($consumer->deactivated_by);
            return [
                'deactivateDate' => Carbon::parse($consumer->deactivation_date)->format('d-m-Y'),
                'consumerNo' => $consumer->consumer_no,
                'consumerName' => $consumer->name,
                'wardNo' => $consumer->ward_no,
                'consumerMobile' => $consumer->mobile_no,
                'deactivateBy' => $user ? $user->name : "",
                'remarks' => $consumer->remarks,
            ];
        });
        $response = [
            'data' => $DeactConsumers,
            'current_page' => $consumers->currentPage(),
            'total' => $consumers->total(),
            'per_page' => $consumers->perPage(),
            'last_page' => $consumers->lastPage(),
            'next_page_url' => $consumers->nextPageUrl(),
            'prev_page_url' => $consumers->previousPageUrl(),
        ];
        return $response;
    }
    public function ConsumerDectOld($From, $Upto, $ulbId, $wardNo = null, $consumerCategory = null, $tcId = null, $consumerType = null)
    {
        $response = array();
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');

        $consumers = $this->ConsumerDeactivateDeatils->latest('id')
            ->select('swm_consumer_deactivates.*', 'name', 'consumer_no', 'mobile_no', 'swm_consumers.ward_no')
            ->join('swm_consumers', 'swm_consumer_deactivates.consumer_id', '=', 'swm_consumers.id')
            ->where('swm_consumer_deactivates.ulb_id', $ulbId)
            ->whereBetween('deactivation_date', [$From, $Upto])
            ->orderBy('swm_consumer_deactivates.id', 'desc')
            ->paginate(1000);
        if (isset($wardNo)) {
            $consumers = $consumers->where('ward_no', $wardNo);
        }
        if (isset($consumerCategory)) {
            $consumers = $consumers->where('consumer_category_id', $consumerCategory);
        }
        if (isset($tcId)) {
            $consumers = $consumers->where('user_id', $tcId);
        }
        foreach ($consumers as $consumer) {
            $user = $this->GetUserDetails($consumer->deactivated_by);
            $val['deactivateDate'] = Carbon::create($consumer->deactivation_date)->format('d-m-Y');
            $val['consumerNo'] = $consumer->consumer_no;
            $val['consumerName'] = $consumer->name;
            $val['wardNo'] = $consumer->ward_no;
            $val['consumerMobile'] = $consumer->mobile_no;
            $val['deactivateBy'] = ($user) ? $user->name : "";
            $val['remarks'] = $consumer->remarks;
            $response[] = $val;
        }
        return $response;
    }

    public function TransactionDeactivate($From, $Upto, $tcId = null, $ulbId, $wardNo = null, $consumerCategory = null, $paymentMode = null, $consumerType = null, Request $request)
    {
        $response = [];
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');
        $perPage = $request->perPage ?? 50;
        // Build query
        $transaction = $this->TransactionDeactivate->latest('id')
            ->select(
                'swm_transaction_deactivates.*',
                'transaction_date',
                'total_payable_amt',
                'swm_transactions.user_id as transby',
                'name',
                'consumer_no',
                'swm_transactions.payment_mode',
                'a.apt_code',
                'a.apt_name',
                'swm_consumers.ward_no',
                'swm_consumers.consumer_type_id'
            )
            ->join('swm_transactions', 'swm_transaction_deactivates.transaction_id', '=', 'swm_transactions.id')
            ->leftJoin('swm_consumers', 'swm_transactions.consumer_id', '=', 'swm_consumers.id')
            ->leftJoin('swm_apartments as a', 'swm_transactions.apartment_id', '=', 'a.id')
            ->where('swm_transactions.ulb_id', $ulbId)
            ->whereBetween('date', [$From, $Upto]);

        // Apply filters
        if (!empty($tcId)) {
            $transaction->where('swm_transactions.user_id', $tcId);
        }
        if (!empty($wardNo)) {
            $transaction->where('swm_consumers.ward_no', $wardNo);
        }
        if (!empty($consumerCategory)) {
            $transaction->where('swm_consumers.consumer_category_id', $consumerCategory);
        }
        if (!empty($paymentMode)) {
            $transaction->where('swm_transactions.payment_mode', $paymentMode);
        }
        if (!empty($consumerType)) {
            $transaction->where('swm_consumers.consumer_type_id', $consumerType);
        }

        // Paginate results
        $transactions = $transaction->paginate($perPage);
        $DeacTransactions = $transactions->map(function ($trans) {
            return [
                'deactivateDate' => Carbon::create($trans->date)->format('d-m-Y'),
                'transactionDate' => Carbon::create($trans->transaction_date)->format('d-m-Y'),
                'amount' => $trans->total_payable_amt,
                'transactionBy' => $this->GetUserDetails($trans->transby)->name ?? '',
                'consumerName' => $trans->name,
                'wardNo' => $trans->ward_no,
                'consumerNo' => $trans->consumer_no,
                'apartmentName' => $trans->apt_name,
                'apartmentCode' => $trans->apt_code,
                'transactionMode' => $trans->payment_mode,
                'deactivateBy' => $this->GetUserDetails($trans->user_id)->name ?? '',
                'remarks' => $trans->remarks,
            ];
        });
        $response = [
            'data' => $DeacTransactions,
            'current_page' => $transactions->currentPage(),
            'total' => $transactions->total(),
            'per_page' => $transactions->perPage(),
            'last_page' => $transactions->lastPage(),
            'next_page_url' => $transactions->nextPageUrl(),
            'prev_page_url' => $transactions->previousPageUrl(),
        ];
        return $response;
    }
    # =============added and Updateed by alok ===============
    public function TransactionDeactivateOld($From, $Upto, $tcId = null, $ulbId, $wardNo = null, $consumerCategory = null, $paymentMode = null, $consumerType = null)
    {
        $response = [];
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');

        // Build query
        $transaction = $this->TransactionDeactivate->latest('id')
            ->select(
                'swm_transaction_deactivates.*',
                'transaction_date',
                'total_payable_amt',
                'swm_transactions.user_id as transby',
                'name',
                'consumer_no',
                'swm_transactions.payment_mode',
                'a.apt_code',
                'a.apt_name',
                'swm_consumers.ward_no',
                'swm_consumers.consumer_type_id'
            )
            ->join('swm_transactions', 'swm_transaction_deactivates.transaction_id', '=', 'swm_transactions.id')
            ->leftJoin('swm_consumers', 'swm_transactions.consumer_id', '=', 'swm_consumers.id')
            ->leftJoin('swm_apartments as a', 'swm_transactions.apartment_id', '=', 'a.id')
            ->where('swm_transactions.ulb_id', $ulbId)
            ->whereBetween('date', [$From, $Upto]);

        // Apply filters
        if (!empty($tcId)) {
            $transaction->where('swm_transactions.user_id', $tcId);
        }
        if (!empty($wardNo)) {
            $transaction->where('swm_consumers.ward_no', $wardNo);
        }
        if (!empty($consumerCategory)) {
            $transaction->where('swm_consumers.consumer_category_id', $consumerCategory);
        }
        if (!empty($paymentMode)) {
            $transaction->where('swm_transactions.payment_mode', $paymentMode);
        }
        if (!empty($consumerType)) {
            $transaction->where('swm_consumers.consumer_type_id', $consumerType);
        }

        // Paginate results
        $transactions = $transaction->paginate(1000);

        // Process each transaction
        foreach ($transactions as $trans) {
            $val['deactivateDate'] = Carbon::create($trans->date)->format('d-m-Y');
            $val['transactionDate'] = Carbon::create($trans->transaction_date)->format('d-m-Y');
            $val['amount'] = $trans->total_payable_amt;
            $val['transactionBy'] = $this->GetUserDetails($trans->transby)->name ?? '';
            $val['consumerName'] = $trans->name;
            $val['wardNo'] = $trans->ward_no;
            $val['consumerNo'] = $trans->consumer_no;
            $val['apartmentName'] = $trans->apt_name;
            $val['apartmentCode'] = $trans->apt_code;
            $val['transactionMode'] = $trans->payment_mode;
            $val['deactivateBy'] = $this->GetUserDetails($trans->user_id)->name ?? '';
            $val['remarks'] = $trans->remarks;
            $response[] = $val;
        }

        return $response;
    }

    public function CashVerification($From, $Upto, $tcId = null, $ulbId, $wardNo = null, $consumerCategory = null, $paymentMode = null, $consumerType = null, Request $request)
    {
        $response = array();
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->addHours(24)->format('Y-m-d');
        $perPage = $request->perPage ?? 50;

        $transaction = $this->TransactionVerification->latest('id')
            ->select(
                'swm_transaction_verifications.*',
                'transaction_date',
                'total_payable_amt',
                'swm_transactions.user_id as transby',
                'name',
                'consumer_no',
                'swm_transactions.payment_mode',
                'a.apt_code',
                'a.apt_name',
                'swm_consumers.ward_no'
            )

            ->join('swm_transactions', 'swm_transaction_verifications.transaction_id', '=', 'swm_transactions.id')
            ->leftjoin('swm_consumers', 'swm_transactions.consumer_id', '=', 'swm_consumers.id')
            ->leftjoin('swm_apartments as a', 'swm_transactions.apartment_id', '=', 'a.id')
            ->where('swm_transactions.ulb_id', $ulbId);

        // Apply filters
        if (!empty($tcId)) {
            $transaction = $transaction->where('swm_transactions.user_id', $tcId);
        }
        if (!empty($wardNo)) {
            $transaction = $transaction->where('swm_consumers.ward_no', $wardNo);
        }
        if (!empty($consumerCategory)) {
            $transaction = $transaction->where('swm_consumers.consumer_category_id', $consumerCategory);
        }
        if (!empty($paymentMode)) {
            $transaction->where('swm_transactions.payment_mode', $paymentMode);
        }
        if (!empty($consumerType)) {
            $transaction->where('swm_consumers.consumer_type_id', $consumerType);
        }

        $transactions = $transaction->whereBetween('verify_date', [$From, $Upto])
            ->paginate($perPage);
        $CashVerify = $transactions->map(function ($trans) {
            return [
                'verifiedDate' => Carbon::create($trans->verify_date)->format('d-m-Y'),
                'transactionDate' => Carbon::create($trans->transaction_date)->format('d-m-Y'),
                'amount' => $trans->amount,
                'transactionBy' => $this->GetUserDetails($trans->transby)->name,
                'consumerName' => $trans->name,
                'consumerNo' => $trans->consumer_no,
                'wardNo' => $trans->ward_no,
                'apartmentName' => $trans->apt_name,
                'apartmentCode' => $trans->apt_code,
                'transactionMode' => $trans->payment_mode,
                'verifiedBy' => $this->GetUserDetails($trans->verify_by)->name,
                'remarks' => $trans->remarks,
            ];
        });
        $response = [
            'data' => $CashVerify,
            'current_page' => $transactions->currentPage(),
            'total' => $transactions->total(),
            'per_page' => $transactions->perPage(),
            'last_page' => $transactions->lastPage(),
            'next_page_url' => $transactions->nextPageUrl(),
            'prev_page_url' => $transactions->previousPageUrl(),
        ];

        return $response;
    }

    # =============added and Updateed by alok =============== 
    public function CashVerificationOld($From, $Upto, $tcId = null, $ulbId, $wardNo = null, $consumerCategory = null, $paymentMode = null, $consumerType = null)
    {
        $response = array();
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->addHours(24)->format('Y-m-d');


        $transaction = $this->TransactionVerification->latest('id')
            ->select(
                'swm_transaction_verifications.*',
                'transaction_date',
                'total_payable_amt',
                'swm_transactions.user_id as transby',
                'name',
                'consumer_no',
                'swm_transactions.payment_mode',
                'a.apt_code',
                'a.apt_name',
                'swm_consumers.ward_no'
            )

            ->join('swm_transactions', 'swm_transaction_verifications.transaction_id', '=', 'swm_transactions.id')
            ->leftjoin('swm_consumers', 'swm_transactions.consumer_id', '=', 'swm_consumers.id')
            ->leftjoin('swm_apartments as a', 'swm_transactions.apartment_id', '=', 'a.id')
            ->where('swm_transactions.ulb_id', $ulbId);

        // Apply filters
        if (!empty($tcId)) {
            $transaction = $transaction->where('swm_transactions.user_id', $tcId);
        }
        if (!empty($wardNo)) {
            $transaction = $transaction->where('swm_consumers.ward_no', $wardNo);
        }
        if (!empty($consumerCategory)) {
            $transaction = $transaction->where('swm_consumers.consumer_category_id', $consumerCategory);
        }
        if (!empty($paymentMode)) {
            $transaction->where('swm_transactions.payment_mode', $paymentMode);
        }
        if (!empty($consumerType)) {
            $transaction->where('swm_consumers.consumer_type_id', $consumerType);
        }

        $transaction = $transaction->whereBetween('verify_date', [$From, $Upto])
            ->paginate(1000);

        foreach ($transaction as $trans) {
            $val['verifiedDate'] = Carbon::create($trans->verify_date)->format('d-m-Y');
            $val['transactionDate'] = Carbon::create($trans->transaction_date)->format('d-m-Y');
            $val['amount'] = $trans->amount;
            $val['transactionBy'] = $this->GetUserDetails($trans->transby)->name;
            $val['consumerName'] = $trans->name;
            $val['consumerNo'] = $trans->consumer_no;
            $val['wardNo'] = $trans->ward_no;
            $val['apartmentName'] = $trans->apt_name;
            $val['apartmentCode'] = $trans->apt_code;
            $val['transactionMode'] = $trans->payment_mode;
            $val['verifiedBy'] = $this->GetUserDetails($trans->verify_by)->name;
            $val['remarks'] = $trans->remarks;
            $response[] = $val;
        }
        return $response;
    }

    // public function BankReconcilliation($From, $Upto, $tcId = null, $ulbId)
    // {
    //     $response = array();
    //     $From = Carbon::create($From)->format('Y-m-d');
    //     $Upto = Carbon::create($Upto)->format('Y-m-d');

    //     $sql = "SELECT reconcile_id,reconcilition_date,t.transaction_no,transaction_date,t.payment_mode,cheque_dd_no, cheque_dd_date, bank_name,branch_name, total_payable_amt,bc.remarks,t.user_id as transby, name, consumer_no, a.apt_code, a.apt_name, bc.user_id as verify_by
    //         FROM  swm_transactions t
    //         JOIN swm_bank_reconcile bc on bc.transaction_id=t.id
    //         LEFT JOIN swm_consumers c on t.consumer_id=c.id
    //         LEFT JOIN swm_apartments a on t.apartment_id=a.id
    //         LEFT JOIN swm_bank_reconcile_details bd on bd.reconcile_id=bc.id
    //         LEFT JOIN swm_transaction_details td on td.transaction_id=t.id
    //         WHERE (transaction_date BETWEEN '$From' and '$Upto') and t.paid_status>0 and t.ulb_id=" . $ulbId;

    //     $transactions = DB::connection($this->dbConn)->select($sql);

    //     foreach ($transactions as $trans) {
    //         $val['clearanceDate'] = ($trans->reconcilition_date) ? Carbon::create($trans->reconcilition_date)->format('d-m-Y') : '';
    //         $val['amount'] = $trans->total_payable_amt;
    //         $val['transactionNo'] = $trans->transaction_no;
    //         $val['transactionDate'] = Carbon::create($trans->transaction_date)->format('d-m-Y');
    //         $val['transactionBy'] = $this->GetUserDetails($trans->transby)->name;
    //         $val['consumerName'] = $trans->name;
    //         $val['consumerNo'] = $trans->consumer_no;
    //         $val['apartmentName'] = $trans->apt_name;
    //         $val['apartmentCode'] = $trans->apt_code;
    //         $val['transactionMode'] = $trans->payment_mode;
    //         $val['chequeNo'] = $trans->cheque_dd_no;
    //         $val['chequeDate'] = ($trans->cheque_dd_date) ? Carbon::create($trans->cheque_dd_date)->format('d-m-Y') : '';
    //         $val['bankName'] = $trans->bank_name;
    //         $val['branchName'] = $trans->branch_name;
    //         $val['verifiedBy'] = $this->GetUserDetails($trans->verify_by)->name;
    //         $val['remarks'] = $trans->remarks;
    //         $response[] = $val;
    //     }
    //     return $response;
    // }

    public function BankReconcilliation($From, $Upto, $tcId = null, $ulbId, $request, $consumerCategory, $consumerType, $mode, $page, $perPage)
    {
        $response = [];
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');
        $offset = ($page - 1) * $perPage;

        // Count total records for pagination
        $countSql = "
        SELECT COUNT(*) as total 
        FROM swm_transactions t
        JOIN swm_bank_reconcile bc ON bc.transaction_id = t.id
        LEFT JOIN swm_consumers c ON t.consumer_id = c.id
        LEFT JOIN swm_apartments a ON t.apartment_id = a.id
        LEFT JOIN swm_bank_reconcile_details bd ON bd.reconcile_id = bc.id
        LEFT JOIN swm_transaction_details td ON td.transaction_id = t.id
        WHERE t.transaction_date BETWEEN ? AND ?
          AND t.paid_status > 0
          AND t.ulb_id = ?
        ";

        $parameters = [$From, $Upto, $ulbId];

        // Apply filters to count query
        if ($consumerCategory) {
            $countSql .= " AND c.consumer_category_id = ?";
            $parameters[] = $consumerCategory;
        }
        if ($consumerType) {
            $countSql .= " AND c.consumer_type_id = ?";
            $parameters[] = $consumerType;
        }
        if (isset($request->wardNo)) {
            $countSql .= " AND c.ward_no = ?";
            $parameters[] = $request->wardNo;
        }
        if (isset($request->mode)) {
            $countSql .= " AND t.payment_mode = ?";
            $parameters[] = $mode;
        }
        if (isset($request->tcId)) {
            $countSql .= " AND t.user_id = ?";
            $parameters[] = $tcId;
        }

        // Get total count
        $totalRecords = DB::connection($this->dbConn)->select($countSql, $parameters)[0]->total;

        // Main Query with Pagination
        $sql = "
        SELECT 
            reconcile_id, 
            reconcilition_date, 
            t.transaction_no, 
            transaction_date, 
            t.payment_mode, 
            cheque_dd_no, 
            cheque_dd_date, 
            bank_name, 
            branch_name, 
            total_payable_amt, 
            bc.remarks, 
            t.user_id as transby, 
            c.name, 
            c.consumer_no, 
            c.ward_no, 
            a.apt_code, 
            a.apt_name, 
            bc.user_id as verify_by
        FROM swm_transactions t
        JOIN swm_bank_reconcile bc ON bc.transaction_id = t.id
        LEFT JOIN swm_consumers c ON t.consumer_id = c.id
        LEFT JOIN swm_apartments a ON t.apartment_id = a.id
        LEFT JOIN swm_bank_reconcile_details bd ON bd.reconcile_id = bc.id
        LEFT JOIN swm_transaction_details td ON td.transaction_id = t.id
        WHERE t.transaction_date BETWEEN ? AND ?
          AND t.paid_status > 0
          AND t.ulb_id = ?
        ";

        // Apply filters to main query
        if ($consumerCategory) {
            $sql .= " AND c.consumer_category_id = ?";
        }
        if ($consumerType) {
            $sql .= " AND c.consumer_type_id = ?";
        }
        if (isset($request->wardNo)) {
            $sql .= " AND c.ward_no = ?";
        }
        if (isset($request->mode)) {
            $sql .= " AND t.payment_mode = ?";
        }
        if (isset($request->tcId)) {
            $sql .= " AND t.user_id = ?";
        }

        // Apply LIMIT and OFFSET for pagination
        $sql .= " ORDER BY t.transaction_date ASC LIMIT ? OFFSET ?";
        $parameters[] = $perPage;
        $parameters[] = $offset;

        // Execute query
        $transactions = DB::connection($this->dbConn)->select($sql, $parameters);

        // Process transactions
        foreach ($transactions as $trans) {
            $val = [
                'clearanceDate' => $trans->reconcilition_date ? Carbon::create($trans->reconcilition_date)->format('d-m-Y') : '',
                'amount' => $trans->total_payable_amt,
                'transactionNo' => $trans->transaction_no,
                'transactionDate' => Carbon::create($trans->transaction_date)->format('d-m-Y'),
                'transactionBy' => $this->GetUserDetails($trans->transby)->name ?? 'Unknown',
                'consumerName' => $trans->name,
                'wardNo' => $trans->ward_no,
                'consumerNo' => $trans->consumer_no,
                'apartmentName' => $trans->apt_name,
                'apartmentCode' => $trans->apt_code,
                'transactionMode' => $trans->payment_mode,
                'chequeNo' => $trans->cheque_dd_no,
                'chequeDate' => $trans->cheque_dd_date ? Carbon::create($trans->cheque_dd_date)->format('d-m-Y') : '',
                'bankName' => $trans->bank_name,
                'branchName' => $trans->branch_name,
                'verifiedBy' => $this->GetUserDetails($trans->verify_by)->name ?? 'Unknown',
                'remarks' => $trans->remarks,
            ];
            $response[] = $val;
        }

        // Return paginated response
        return [
            'data' => $response,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $totalRecords,
            'last_page' => ceil($totalRecords / $perPage),
            'next_page_url' => ($page * $perPage < $totalRecords) ? url()->current() . '?page=' . ($page + 1) : null,
            'prev_page_url' => ($page > 1) ? url()->current() . '?page=' . ($page - 1) : null,
        ];
    }


    #=============some modification by alok=====================
    public function BankReconcilliationOld($From, $Upto, $tcId = null, $ulbId, $request, $consumerCategory, $consumerType, $mode)
    {
        $response = [];
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');

        // Base SQL Query
        $sql = "
        SELECT 
            reconcile_id, 
            reconcilition_date, 
            t.transaction_no, 
            transaction_date, 
            t.payment_mode, 
            cheque_dd_no, 
            cheque_dd_date, 
            bank_name, 
            branch_name, 
            total_payable_amt, 
            bc.remarks, 
            t.user_id as transby, 
            c.name, 
            c.consumer_no, 
            c.ward_no, 
            a.apt_code, 
            a.apt_name, 
            bc.user_id as verify_by
        FROM swm_transactions t
        JOIN swm_bank_reconcile bc ON bc.transaction_id = t.id
        LEFT JOIN swm_consumers c ON t.consumer_id = c.id
        LEFT JOIN swm_apartments a ON t.apartment_id = a.id
        LEFT JOIN swm_bank_reconcile_details bd ON bd.reconcile_id = bc.id
        LEFT JOIN swm_transaction_details td ON td.transaction_id = t.id
        WHERE t.transaction_date BETWEEN ? AND ?
          AND t.paid_status > 0
          AND t.ulb_id = ?
    ";

        $parameters = [$From, $Upto, $ulbId];
        if ($consumerCategory) {
            $sql .= " AND c.consumer_category_id = ?";
            $parameters[] = $consumerCategory;
        }
        if (isset($request->wardNo)) {
            $sql .= " AND c.ward_no = ?";
            $parameters[] = $request->wardNo;
        }
        $transactions = DB::connection($this->dbConn)->select($sql, $parameters);
        foreach ($transactions as $trans) {
            $val = [
                'clearanceDate' => $trans->reconcilition_date ? Carbon::create($trans->reconcilition_date)->format('d-m-Y') : '',
                'amount' => $trans->total_payable_amt,
                'transactionNo' => $trans->transaction_no,
                'transactionDate' => Carbon::create($trans->transaction_date)->format('d-m-Y'),
                'transactionBy' => $this->GetUserDetails($trans->transby)->name ?? 'Unknown',
                'consumerName' => $trans->name,
                'wardNo' => $trans->ward_no,
                'consumerNo' => $trans->consumer_no,
                'apartmentName' => $trans->apt_name,
                'apartmentCode' => $trans->apt_code,
                'transactionMode' => $trans->payment_mode,
                'chequeNo' => $trans->cheque_dd_no,
                'chequeDate' => $trans->cheque_dd_date ? Carbon::create($trans->cheque_dd_date)->format('d-m-Y') : '',
                'bankName' => $trans->bank_name,
                'branchName' => $trans->branch_name,
                'verifiedBy' => $this->GetUserDetails($trans->verify_by)->name ?? 'Unknown',
                'remarks' => $trans->remarks,
            ];
            $response[] = $val;
        }

        return $response;
    }


    public function TcDailyActivityold($From, $Upto, $tcId, $ulbId, $consumerCategory)
    {
        $response = array();
        $From = Carbon::create($From);
        $Upto = Carbon::create($Upto);

        $tc_details = $this->GetUserDetails($tcId);
        $response['tcName'] = ($tc_details) ? $tc_details->name : "";
        $response['mobileNo'] = ($tc_details) ? $tc_details->contactno : "";
        $response['userType'] = ($tc_details) ? $tc_details->user_type : "";

        $maindata = array();


        for ($i = $From; $i <= $Upto; $i->modify('+1 day')) {
            $loginarr = array();
            $transarr = array();
            $denayarr = array();
            $denayamountarr = array();
            $collectionarr = array();
            $date = $i->format("Y-m-d");
            $val['date'] = $date;

            $user_login = UserLoginDetail::where('user_id', $tcId)
                ->whereDate('timestamp', $date)
                ->get();

            foreach ($user_login as $log) {
                $loginarr[] = $log->login_time;
            }

            $consumer_count = $this->Consumer->where('user_id', $tcId)
                ->whereDate('entry_date', $date)
                ->where('ulb_id', $ulbId)
                ->count();
            if (isset($consumer)) {
                $consumer_count->where('swm_consumer.consumer_category_id', $consumerCategory);
            }


            $trans = $this->Transaction->where('user_id', $tcId)
                ->whereDate('transaction_date', $date)
                ->where('ulb_id', $ulbId)
                ->get();
            foreach ($trans as $t) {
                $collectionarr[] = $t->total_payable_amt;
                $transarr[] = Carbon::create($t->stampdate)->format('h:i:s a');
            }

            $deny = $this->PaymentDeny->where('user_id', $tcId)
                ->whereDate('deny_date', $date)
                ->where('ulb_id', $ulbId)
                ->get();

            foreach ($deny as $d) {
                $denayamountarr[] = $d->outstanding_amount;
                $denayarr[] = Carbon::create($d->deny_date)->format('h:i:s a');
            }

            if ($loginarr) {
                $val['loginTime'] = $loginarr;
                $val['addedConsumerQuantity'] = $consumer_count;
                $val['collectionTime'] = $transarr;
                $val['collectionAmount'] = $collectionarr;
                $val['paymentDeniedTime'] = $denayarr;
                $val['paymentDeniedAmount'] = $denayamountarr;
                $maindata[] = $val;
            }
        }
        $response['data'] = $maindata;

        return $response;
    }

    public function TcDailyActivity($From, $Upto, $tcId = null, $ulbId, $consumerCategory = null, Request $request)
    {
        $From = Carbon::create($From)->startOfDay();
        $Upto = Carbon::create($Upto)->endOfDay();
        $tcList = $this->GetUserDetailsNew($tcId);

        if (empty($tcList)) {
            return ['data' => [], 'message' => 'No users found'];
        }

        $tcIds = collect($tcList)->pluck('id')->toArray(); // Get user IDs in an array
        $dates = collect(range(0, $From->diffInDays($Upto)))->map(fn($i) => $From->copy()->addDays($i)->format('Y-m-d'));

        // Fetch all required data in batch queries
        $userLogins = UserLoginDetail::whereIn('user_id', $tcIds)
            ->whereBetween('timestamp', [$From, $Upto])
            ->select('user_id', DB::raw("DATE(timestamp) as date"), 'login_time')
            ->orderBy('timestamp', 'ASC')
            ->get()
            ->groupBy(['user_id', 'date']);

        $consumerCounts = $this->Consumer
            ->whereIn('user_id', $tcIds)
            ->whereBetween('entry_date', [$From, $Upto])
            ->where('ulb_id', $ulbId)
            ->when($consumerCategory, fn($query) => $query->where('swm_consumers.consumer_category_id', $consumerCategory))
            ->select('user_id', DB::raw("DATE(entry_date) as date"), DB::raw("COUNT(*) as count"))
            ->groupBy('user_id', 'date')
            ->get()
            ->keyBy(fn($item) => $item->user_id . '_' . $item->date);

        $transactions = $this->Transaction
            ->whereIn('user_id', $tcIds)
            ->whereBetween('transaction_date', [$From, $Upto])
            ->where('ulb_id', $ulbId)
            ->select('user_id', DB::raw("DATE(transaction_date) as date"), 'total_payable_amt', 'stampdate')
            ->orderBy('stampdate', 'ASC')
            ->get()
            ->groupBy(['user_id', 'date']);

        $deniedPayments = $this->PaymentDeny
            ->whereIn('user_id', $tcIds)
            ->whereBetween('deny_date', [$From, $Upto])
            ->where('ulb_id', $ulbId)
            ->select('user_id', DB::raw("DATE(deny_date) as date"), 'outstanding_amount', 'deny_date')
            ->orderBy('deny_date', 'ASC')
            ->get()
            ->groupBy(['user_id', 'date']);

        // Organizing the data for response
        $allTcData = [];
        foreach ($tcList as $tc) {
            $tcData = [
                'tcName' => $tc->name,
                'mobileNo' => $tc->contactno,
                'userType' => $tc->user_type,
                'data' => []
            ];

            foreach ($dates as $date) {
                $tcId = $tc->id;
                $key = "{$tcId}_{$date}";

                $tcData['data'][] = [
                    'date' => $date,
                    'loginTime' => $userLogins[$tcId][$date] ?? [],
                    'addedConsumerQuantity' => $consumerCounts[$key]->count ?? 0,
                    'collectionTime' => isset($transactions[$tcId][$date]) ? $transactions[$tcId][$date]->pluck('stampdate')->map(fn($t) => Carbon::parse($t)->format('h:i:s a')) : [],
                    'collectionAmount' => isset($transactions[$tcId][$date]) ? $transactions[$tcId][$date]->pluck('total_payable_amt') : [],
                    'paymentDeniedTime' => isset($deniedPayments[$tcId][$date]) ? $deniedPayments[$tcId][$date]->pluck('deny_date')->map(fn($t) => Carbon::parse($t)->format('h:i:s a')) : [],
                    'paymentDeniedAmount' => isset($deniedPayments[$tcId][$date]) ? $deniedPayments[$tcId][$date]->pluck('outstanding_amount') : [],
                ];
            }

            $allTcData[] = $tcData;
        }

        $perPage = $request->perPage ?? 10;
        $page = request()->get('page', 1);
        $total = count($allTcData);
        $paginatedData = array_slice($allTcData, ($page - 1) * $perPage, $perPage);

        return [
            'data' => $paginatedData,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
            'next_page_url' => $page < ceil($total / $perPage) ? url()->current() . '?page=' . ($page + 1) : null,
            'prev_page_url' => $page > 1 ? url()->current() . '?page=' . ($page - 1) : null,
        ];
    }

    public function TransactionModeChange($From, $Upto, $tcId = null, $ulbId, $consumerCategory, $request = null)
    {
        $response = array();
        $From = Carbon::create($From);
        $Upto = Carbon::create($Upto);
        $perPage = $request->perPage ?? 50;
        $mchange = $this->TransactionModeChange->select('swm_log_transaction_mode.*', 'transaction_date', 'total_payable_amt as amount', 't.user_id as transby', 'c.name as consumer_name', 'c.consumer_no', 'a.apt_name', 'a.apt_code')
            ->join('swm_transactions as t', 'swm_log_transaction_mode.transaction_id', '=', 't.id')
            ->leftjoin('swm_consumers as c', 't.consumer_id', '=', 'c.id')
            ->leftjoin('swm_apartments as a', 't.apartment_id', '=', 'a.id')
            ->whereBetween('swm_log_transaction_mode.date', [$From, $Upto])
            ->where('swm_log_transaction_mode.is_deactivate', 0)
            ->where('t.ulb_id', $ulbId);
        if (isset($tcId)) {
            $mchange = $mchange->where('swm_log_transaction_mode.user_id', $tcId);
        }
        if (isset($consumerCategory)) {
            $mchange = $mchange->where('c.consumer_category_id', $consumerCategory);
        }
        $mchange = $mchange->latest('swm_log_transaction_mode.id')->paginate($perPage);
        $ModeData = $mchange->map(function ($trans) {
            return [
                'changeDate' => Carbon::parse($trans->date)->format('d-m-Y'),
                'transactionDate' => Carbon::parse($trans->transaction_date)->format('d-m-Y'),
                'amount' => $trans->amount,
                'transactionBy' => $trans->transby ? $this->GetUserDetails($trans->transby)->name : 'Unknown',
                'consumerName' => $trans->consumer_name ?? 'N/A',
                'consumerNo' => $trans->consumer_no ?? 'N/A',
                'apartmentName' => $trans->apt_name ?? 'N/A',
                'apartmentCode' => $trans->apt_code ?? 'N/A',
                'oldTransactionMode' => $trans->previous_mode ?? 'N/A',
                'newTransactionMode' => $trans->current_mode ?? 'N/A',
                'changeBy' => $trans->user_id ? $this->GetUserDetails($trans->user_id)->name : 'Unknown',
            ];
        });

        $response = [
            'data' => $ModeData,
            'current_page' => $mchange->currentPage(),
            'per_page' => $mchange->perPage(),
            'total' => $mchange->total(),
            'last_page' => $mchange->lastPage(),
            'next_page_url' => $mchange->nextPageUrl(),
            'prev_page_url' => $mchange->previousPageUrl(),
        ];

        return $response;
    }
    public function TransactionModeChangeOld($From, $Upto, $tcId = null, $ulbId, $consumerCategory)
    {
        $response = array();
        $From = Carbon::create($From);
        $Upto = Carbon::create($Upto);

        $mchange = $this->TransactionModeChange->select('swm_log_transaction_mode.*', 'transaction_date', 'total_payable_amt as amount', 't.user_id as transby', 'c.name as consumer_name', 'c.consumer_no', 'a.apt_name', 'a.apt_code')
            ->join('swm_transactions as t', 'swm_log_transaction_mode.transaction_id', '=', 't.id')
            ->leftjoin('swm_consumers as c', 't.consumer_id', '=', 'c.id')
            ->leftjoin('swm_apartments as a', 't.apartment_id', '=', 'a.id')
            ->whereBetween('swm_log_transaction_mode.date', [$From, $Upto])
            ->where('swm_log_transaction_mode.is_deactivate', 0)
            ->where('t.ulb_id', $ulbId);
        if (isset($tcId))
            $mchange = $mchange->where('swm_log_transaction_mode.user_id', $tcId);
        $mchange = $mchange->where('swm_consumers.consumer_category_id', $consumerCategory);
        $mchange = $mchange->latest('swm_log_transaction_mode.id')->get();

        foreach ($mchange as $trans) {
            $val['changeDate'] = Carbon::create($trans->date)->format('d-m-Y');
            $val['transactionDate'] = Carbon::create($trans->transaction_date)->format('d-m-Y');
            $val['amount'] = $trans->amount;
            $val['transactionBy'] = $this->GetUserDetails($trans->transby)->name;
            $val['consumerName'] = $trans->consumer_name;
            $val['consumerNo'] = $trans->consumer_no;
            $val['apartmentName'] = $trans->apt_name;
            $val['apartmentCode'] = $trans->apt_code;
            $val['oldTransactionMode'] = $trans->previous_mode;
            $val['newTransactionMode'] = $trans->current_mode;
            $val['changeBy'] = $this->GetUserDetails($trans->user_id)->name;
            $response[] = $val;
        }

        return $response;
    }


    public function consumerEditLog($From, $Upto, $tcId = null, $ulbId, $wardNo = null, $consumerCategory = null, $consumertype = null, Request $request)
    {
        $response = [];

        // Format date inputs
        $From = Carbon::parse($From)->format('Y-m-d 00:00:01');
        $Upto = Carbon::parse($Upto)->format('Y-m-d 23:59:59');
        $perPage = $request->perPage ?? 50;
        // Build initial query
        $query = $this->mConsumerEditLog
            ->select(
                'swm_log_consumers.id',
                'swm_consumers.consumer_no',
                'swm_consumers.ward_no',
                'swm_consumers.name',
                'swm_consumers.mobile_no',
                'swm_consumers.address',
                'swm_consumers.pincode',
                'swm_log_consumers.stampdate',
                'swm_log_consumers.user_id',
                'swm_consumer_categories.name as consumerCategory',
                'swm_consumer_types.name as consumerType'
            )
            ->join('swm_consumers', 'swm_consumers.id', '=', 'swm_log_consumers.consumer_id')
            ->join('swm_consumer_categories', 'swm_consumer_categories.id', '=', 'swm_consumers.consumer_category_id')
            ->join('swm_consumer_types', 'swm_consumer_types.id', '=', 'swm_consumers.consumer_type_id')
            ->where('swm_log_consumers.ulb_id', $ulbId)
            ->whereBetween('swm_log_consumers.stampdate', [$From, $Upto]);

        // Apply filters
        if (!empty($tcId)) {
            $query->where('swm_log_consumers.user_id', $tcId);
        }
        if (!empty($wardNo)) {
            $query->where('swm_consumers.ward_no', $wardNo);
        }
        if (!empty($consumerCategory)) {
            $query->where('swm_consumers.consumer_category_id', $consumerCategory);
        }
        if (!empty($consumertype)) {
            $query->where('swm_consumers.consumer_type_id', $consumertype);
        }

        // Fetch results
        $mchange = $query->paginate($perPage);
        $consumerLog = $mchange->map(function ($detail) {
            $user = $this->GetUserDetails($detail->user_id);
            return [
                'id' => $detail->id,
                'consumer_no' => $detail->consumer_no,
                'ward_no' => $detail->ward_no,
                'mobile_no' => $detail->mobile_no,
                'address' => $detail->address,
                'pincode' => $detail->pincode,
                'consumerCategory' => $detail->consumerCategory,
                'consumerType' => $detail->consumerType,
                'changeBy' => $user ? $user->name : "",
                'changedDate' => Carbon::parse($detail->stampdate)->format('d-m-Y h:i A'),
            ];
        });
        $response = [
            'data' => $consumerLog,
            'current_page' => $mchange->currentPage(),
            'total' => $mchange->total(),
            'per_page' => $mchange->perPage(),
            'last_page' => $mchange->lastPage(),
            'next_page_url' => $mchange->nextPageUrl(),
            'prev_page_url' => $mchange->previousPageUrl(),
        ];
        return $response;
    }
    # =============added and Updateed by alok ===============
    public function consumerEditLogOld($From, $Upto, $tcId = null, $ulbId, $wardNo = null, $consumerCategory = null, $consumertype = null)
    {
        $response = [];

        // Format date inputs
        $From = Carbon::parse($From)->format('Y-m-d 00:00:01');
        $Upto = Carbon::parse($Upto)->format('Y-m-d 23:59:59');

        // Build initial query
        $query = $this->mConsumerEditLog
            ->select(
                'swm_log_consumers.id',
                'swm_consumers.consumer_no',
                'swm_consumers.ward_no',
                'swm_consumers.name',
                'swm_consumers.mobile_no',
                'swm_consumers.address',
                'swm_consumers.pincode',
                'swm_log_consumers.stampdate',
                'swm_log_consumers.user_id',
                'swm_consumer_categories.name as consumerCategory',
                'swm_consumer_types.name as consumerType'
            )
            ->join('swm_consumers', 'swm_consumers.id', '=', 'swm_log_consumers.consumer_id')
            ->join('swm_consumer_categories', 'swm_consumer_categories.id', '=', 'swm_consumers.consumer_category_id')
            ->join('swm_consumer_types', 'swm_consumer_types.id', '=', 'swm_consumers.consumer_type_id')
            ->where('swm_log_consumers.ulb_id', $ulbId)
            ->whereBetween('swm_log_consumers.stampdate', [$From, $Upto]);

        // Apply filters
        if (!empty($tcId)) {
            $query->where('swm_log_consumers.user_id', $tcId);
        }
        if (!empty($wardNo)) {
            $query->where('swm_consumers.ward_no', $wardNo);
        }
        if (!empty($consumerCategory)) {
            $query->where('swm_consumers.consumer_category_id', $consumerCategory);
        }
        if (!empty($consumertype)) {
            $query->where('swm_consumers.consumer_type_id', $consumertype);
        }

        // Fetch results
        $mchange = $query->get();

        // Process each entry
        foreach ($mchange as $detail) {
            $user = $this->GetUserDetails($detail->user_id);
            $response[] = [
                'id'              => $detail->id,
                'consumer_no'     => $detail->consumer_no,
                'ward_no'         => $detail->ward_no,
                'mobile_no'       => $detail->mobile_no,
                'address'         => $detail->address,
                'pincode'         => $detail->pincode,
                'consumerCategory' => $detail->consumerCategory,
                'consumerType'    => $detail->consumerType,
                'changeBy'        => $user ? $user->name : "",
                'changedDate'     => Carbon::parse($detail->stampdate)->format('d-m-Y h:i A'),
            ];
        }

        return $response;
    }




    // public function monthlyComparison($request, $consumerCategory, $tcId)
    // {
    //     $perPage = $request->perPage ?? 10;
    //     $wardNo  = $request->wardNo ?? 1;
    //     $toDate  = isset($request->toDate) ? $request->toDate . '-01' : Carbon::now()->startOfMonth()->format('Y-m-d');

    //     $currentMonth = Carbon::parse($toDate)->subMonths(2)->format('m');
    //     $currentYear  = Carbon::parse($toDate)->format('Y');

    //     $consumerDtls = $this->Consumer
    //         ->select(
    //             'id',
    //             'ward_no',
    //             'consumer_no',
    //             'mobile_no',
    //             'name'
    //         )
    //         ->where('swm_consumers.ward_no', $wardNo);

    //     if (isset($consumerCategory)) {
    //         $consumerDtls->where('swm_consumers.consumer_category_id', $consumerCategory);
    //     }
    //     if (isset($tcId)) {
    //         $consumerDtls->where('swm_consumers.user_id', $tcId);
    //     }

    //     $consumerDtls = $consumerDtls->paginate($perPage);

    //     foreach ($consumerDtls as $consumer) {

    //         $demandDtls = $this->mDemand
    //             ->select(
    //                 'swm_demands.id as demand_id',
    //                 'consumer_id',
    //                 'total_tax',
    //                 'payment_from',
    //                 'paid_status',
    //                 (DB::raw("TO_CHAR(payment_from, 'Month') || ' ' || TO_CHAR(payment_from, 'YYYY') as month_year")),
    //                 // DB::raw('TO_CHAR(payment_from, \'Month\') as month'),
    //                 // DB::raw('EXTRACT(MONTH from payment_from) as month'),
    //             )
    //             ->where('swm_demands.consumer_id', $consumer->id)
    //             ->where('swm_demands.is_deactivate', 0)
    //             // ->whereDate('payment_from', '>=', Carbon::now()->subMonths(3)->startOfMonth())
    //             ->whereMonth('payment_from', '>=',  $currentMonth)
    //             ->whereYear('payment_from', $currentYear)
    //             ->orderByDesc('swm_demands.id')
    //             ->take(3)
    //             ->get();

    //         if (collect($demandDtls)->isEmpty())
    //             continue;

    //         $val['consumer_id']           = $consumer->id;
    //         $val['consumer_ward_no']      = $consumer->ward_no;
    //         $val['consumer_consumer_no']  = $consumer->consumer_no;
    //         $val['consumer_mobile_no']    = $consumer->mobile_no;
    //         $val['consumer_name']         = $consumer->name;
    //         $val['demandDtls']            = $demandDtls;
    //         $transactions[]               = $val;
    //     }

    //     $data['data']         = $transactions;
    //     $data['current_page'] = $consumerDtls->currentPage();
    //     $data['last_page']    = $consumerDtls->lastPage();
    //     $data['total']        = $consumerDtls->total();
    //     $data['per_page']     = $perPage;

    //     return $this->responseMsgs(true, "Data Fetched", $data);
    // }
    public function monthlyComparison($request)
    {
        $perPage = $request->perPage ?? 10;
        $wardNo  = $request->wardNo ?? 1;
        $toDate  = isset($request->toDate) ? $request->toDate . '-01' : Carbon::now()->startOfMonth()->format('Y-m-d');

        $currentMonth = Carbon::parse($toDate)->subMonths(2)->format('m');
        $currentYear  = Carbon::parse($toDate)->format('Y');

        $consumerDtls = $this->Consumer
            ->select(
                'id',
                'ward_no',
                'consumer_no',
                'mobile_no',
                'name'
            )
            ->where('swm_consumers.ward_no', $wardNo);

        if (isset($consumerCategory)) {
            $consumerDtls->where('swm_consumers.consumer_category_id', $consumerCategory);
        }
        if (isset($tcId)) {
            $consumerDtls->where('swm_consumers.user_id', $tcId);
        }

        $consumerDtls = $consumerDtls->paginate($perPage);

        foreach ($consumerDtls as $consumer) {

            $demandDtls = $this->mDemand
                ->select(
                    'swm_demands.id as demand_id',
                    'consumer_id',
                    'total_tax',
                    'payment_from',
                    'paid_status',
                    (DB::raw("TO_CHAR(payment_from, 'Month') || ' ' || TO_CHAR(payment_from, 'YYYY') as month_year")),
                    // DB::raw('TO_CHAR(payment_from, \'Month\') as month'),
                    // DB::raw('EXTRACT(MONTH from payment_from) as month'),
                )
                ->where('swm_demands.consumer_id', $consumer->id)
                ->where('swm_demands.is_deactivate', 0)
                // ->whereDate('payment_from', '>=', Carbon::now()->subMonths(3)->startOfMonth())
                ->whereMonth('payment_from', '>=',  $currentMonth)
                ->whereYear('payment_from', $currentYear)
                ->orderByDesc('swm_demands.id')
                ->take(3)
                ->get();

            if (collect($demandDtls)->isEmpty())
                continue;

            $val['consumer_id']           = $consumer->id;
            $val['consumer_ward_no']      = $consumer->ward_no;
            $val['consumer_consumer_no']  = $consumer->consumer_no;
            $val['consumer_mobile_no']    = $consumer->mobile_no;
            $val['consumer_name']         = $consumer->name;
            $val['demandDtls']            = $demandDtls;
            $transactions[]               = $val;
        }

        $data['data']         = $transactions;
        $data['current_page'] = $consumerDtls->currentPage();
        $data['last_page']    = $consumerDtls->lastPage();
        $data['total']        = $consumerDtls->total();
        $data['per_page']     = $perPage;

        return $this->responseMsgs(true, "Data Fetched", $data);
    }

    /**
     * | Edit Log Detail
     */
    public function consumerEditLogDetails(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            ["id"   => "required"]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {

            $logDetail = $this->mConsumerEditLog
                ->select(
                    "ward_no",
                    "name as consumer_name",
                    "holding_no",
                    "mobile_no",
                    "address",
                    "consumer_no",
                    "pincode",
                    "license_no",
                    "previous_ward_no",
                    "previous_consumer_name",
                    "previous_holding_no",
                    "previous_mobile_no",
                    "previous_address",
                    "previous_consumer_no",
                    "previous_pincode",
                    "previous_license_no",
                    "user_id",
                    "stampdate"
                )
                ->where('swm_log_consumers.id', $req->id)
                ->first();

            $data = $this->comparison($logDetail);

            // return $this->responseMsgs(true, "Edit Log Details", $data,"consumerNo"=>$logDetail->consumer_no);
            return response()->json([
                'status' => true,
                'msg'    => "Edit Log Details",
                'data'   => $data,
                "consumerNo" => $logDetail->consumer_no
            ]);
        } catch (Exception $e) {
            return $this->responseMsgs(true,  $e->getMessage(), "");
        }
    }

    /**
     * | Comparison
     */
    public function comparison($logDetail)
    {
        $changeBy    = $this->GetUserDetails($logDetail->user_id)->name;
        $changedDate = Carbon::create($logDetail->stampdate)->format('d-m-Y');
        $changedTime = Carbon::create($logDetail->stampdate)->format('h:i A');
        return new Collection([
            ['displayString' => 'Ward No',              'final' => $logDetail->ward_no,           'applied' => $logDetail->previous_ward_no,],
            ['displayString' => 'Consumer Name',        'final' => $logDetail->consumer_name,     'applied' => $logDetail->previous_consumer_name,],
            ['displayString' => 'Holding No',           'final' => $logDetail->holding_no,        'applied' => $logDetail->previous_holding_no,],
            ['displayString' => 'Mobile No',            'final' => $logDetail->mobile_no,         'applied' => $logDetail->previous_mobile_no,],
            ['displayString' => 'Address',              'final' => $logDetail->address,           'applied' => $logDetail->previous_address,],
            // ['displayString' => 'Consumer No',          'final' => $logDetail->consumer_no,       'applied' => $logDetail->previous_consumer_no,],
            ['displayString' => 'Pincode',              'final' => $logDetail->pincode,           'applied' => $logDetail->previous_pincode,],
            ['displayString' => 'License No',           'final' => $logDetail->license_no,        'applied' => $logDetail->previous_license_no,],
            ['displayString' => 'Edited By',            'final' => $changeBy,                     'applied' => 'NA',],
            ['displayString' => 'Edit Date',            'final' => $changedDate,                  'applied' => 'NA',],
            ['displayString' => 'Edit Time',            'final' => $changedTime,                  'applied' => 'NA',],
        ]);
    }

    // public function DemandReceipt(Request $request)
    // {
    //     try {
    //         $response = array();
    //         $user = Auth()->user();
    //         $ulbId = $user->ulb_id ?? 11;
    //         $userId = $user->id;
    //         $whereParam = "";
    //         $whereParam1 = "";
    //         $whereConsumer = "";

    //         if (isset($request->fromDate) && isset($request->toDate)) {
    //             $fromDate = Carbon::create($request->fromDate)->format('Y-m-d');
    //             $toDate = Carbon::create($request->toDate)->format('Y-m-d');
    //             $whereParam .= " and (DATE(print_datetime) between '" . $fromDate . "' and '" . $toDate . "')";
    //         }
    //         if (isset($request->tcId))
    //             $whereParam .= " and printed_by=" . $request->tcId;

    //         if (isset($request->wardNo)) {
    //             $whereParam1 .= " and a.ward_no='" . $request->wardNo . "'";
    //             $whereConsumer .= " and c.ward_no='" . $request->wardNo . "'";
    //         }

    //         if (isset($request->category))
    //             $whereConsumer .= " and consumer_category_id=" . $request->category;

    //         if (isset($request->type))
    //             $whereConsumer .= " and consumer_type_id=" . $request->type;

    //         $sql = "SELECT d.*,c.consumer_no,c.name,c.ward_no,c.address,a.apt_code,a.apt_name,a.ward_no as apt_ward_no,a.apt_address from swm_log_demand_receipts d 
    //                 LEFT JOIN swm_consumers c on d.consumer_id=c.id " . $whereConsumer . "
    //                 LEFT JOIN swm_apartments a on d.apartment_id=a.id " . $whereParam1 . "
    //                 WHERE d.ulb_id = " . $ulbId . " " . $whereParam . "
    //                 ORDER BY print_datetime desc";

    //         $demandLog = DB::connection($this->dbConn)->select($sql);

    //         foreach ($demandLog as $d) {
    //             $val['receiptNo'] = $d->receipt_no;
    //             $val['consumerNo'] = ($d->consumer_id > 0) ? $d->consumer_no : "";
    //             $val['consumerName'] = ($d->consumer_id > 0) ? $d->name : "";
    //             $val['apartmentCode'] = ($d->apartment_id > 0) ? $d->apt_code : "";
    //             $val['apartmentName'] = ($d->apartment_id > 0) ? $d->apt_name : "";
    //             $val['wardNo'] = ($d->ward_no) ? $d->ward_no : $d->apt_ward_no;
    //             $val['address'] = ($d->address) ? $d->address : $d->apt_address;
    //             $val['printedBy'] = $this->GetUserDetails($d->printed_by)->name ?? "";
    //             $val['printDateTime'] = date('d-m-Y h:i A', strtotime($d->print_datetime));
    //             $val['amount'] = $d->amount;
    //             $response[] = $val;
    //         }

    //         return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
    //     } catch (Exception $e) {
    //         return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
    //     }
    // }


    public function DemandReceipt(Request $request)
    {
        try {
            $response = array();
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? 11;
            $perPage = $request->perPage ?? 50; // Number of records per page
            $page = $request->page ?? 1; // Current page
            $fromDate = $request->fromDate ? Carbon::create($request->fromDate)->format('Y-m-d') : null;
            $toDate = $request->toDate ? Carbon::create($request->toDate)->format('Y-m-d') : null;

            $query = DB::connection($this->dbConn)
                ->table('swm_log_demand_receipts as d')
                ->leftJoin('swm_consumers as c', 'd.consumer_id', '=', 'c.id')
                ->leftJoin('swm_apartments as a', 'd.apartment_id', '=', 'a.id')
                ->select(
                    'd.*',
                    'c.consumer_no',
                    'c.name',
                    'c.ward_no',
                    'c.address',
                    'a.apt_code',
                    'a.apt_name',
                    'a.ward_no as apt_ward_no',
                    'a.apt_address'
                )
                ->where('d.ulb_id', $ulbId);

            if ($fromDate && $toDate) {
                $query->whereBetween('print_datetime', [$fromDate, $toDate]);
            }

            if ($request->wardNo) {
                $query->where('a.ward_no', $request->wardNo);
                $query->where('c.ward_no', $request->wardNo);
            }

            if ($request->category) {
                $query->where('c.consumer_category_id', $request->category);
            }

            if ($request->type) {
                $query->where('c.consumer_type_id', $request->type);
            }
            if ($request->tcId) {
                $query->where('printed_by', $request->tcId);
            }


            $demandLog = $query->orderBy('print_datetime', 'desc')->paginate($perPage, ['*'], 'page', $page);

            foreach ($demandLog as $d) {
                $val['receiptNo'] = $d->receipt_no;
                $val['consumerNo'] = ($d->consumer_id > 0) ? $d->consumer_no : "";
                $val['consumerName'] = ($d->consumer_id > 0) ? $d->name : "";
                $val['apartmentCode'] = ($d->apartment_id > 0) ? $d->apt_code : "";
                $val['apartmentName'] = ($d->apartment_id > 0) ? $d->apt_name : "";
                $val['wardNo'] = ($d->ward_no) ? $d->ward_no : $d->apt_ward_no;
                $val['address'] = ($d->address) ? $d->address : $d->apt_address;
                $val['printedBy'] = $this->GetUserDetails($d->printed_by)->name ?? "";
                $val['printDateTime'] = date('d-m-Y h:i A', strtotime($d->print_datetime));
                $val['amount'] = $d->amount;
                $response[] = $val;
            }
            return response()->json([
                'status' => true,
                'data' => [
                    'data' => $response,
                    'current_page' => $demandLog->currentPage(),
                    'total' => $demandLog->total(),
                    'per_page' => $demandLog->perPage(),
                    'last_page' => $demandLog->lastPage(),
                    'next_page_url' => $demandLog->nextPageUrl(),
                    'prev_page_url' => $demandLog->previousPageUrl(),
                ],
                'msg' => ''
            ], 200);
            return response()->json(['status' => True, 'data' => ['data' => $response], 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }
    # =============added and Updateed by alok ===============
    public function DemandReceiptOld(Request $request)
    {
        try {
            $response = array();
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? 11;

            $fromDate = $request->fromDate ? Carbon::create($request->fromDate)->format('Y-m-d') : null;
            $toDate = $request->toDate ? Carbon::create($request->toDate)->format('Y-m-d') : null;

            $query = DB::connection($this->dbConn)->table('swm_log_demand_receipts as d')
                ->leftJoin('swm_consumers as c', 'd.consumer_id', '=', 'c.id')
                ->leftJoin('swm_apartments as a', 'd.apartment_id', '=', 'a.id')
                ->select(
                    'd.*',
                    'c.consumer_no',
                    'c.name',
                    'c.ward_no',
                    'c.address',
                    'a.apt_code',
                    'a.apt_name',
                    'a.ward_no as apt_ward_no',
                    'a.apt_address'
                )
                ->where('d.ulb_id', $ulbId);

            if ($fromDate && $toDate) {
                $query->whereBetween('print_datetime', [$fromDate, $toDate]);
            }

            if ($request->wardNo) {
                $query->where('a.ward_no', $request->wardNo);
                $query->where('c.ward_no', $request->wardNo);
            }

            if ($request->category) {
                $query->where('c.consumer_category_id', $request->category);
            }

            if ($request->type) {
                $query->where('c.consumer_type_id', $request->type);
            }
            if ($request->tcId) {
                $query->where('printed_by', $request->tcId);
            }


            $demandLog = $query->orderBy('print_datetime', 'desc')->get();

            foreach ($demandLog as $d) {
                $val['receiptNo'] = $d->receipt_no;
                $val['consumerNo'] = ($d->consumer_id > 0) ? $d->consumer_no : "";
                $val['consumerName'] = ($d->consumer_id > 0) ? $d->name : "";
                $val['apartmentCode'] = ($d->apartment_id > 0) ? $d->apt_code : "";
                $val['apartmentName'] = ($d->apartment_id > 0) ? $d->apt_name : "";
                $val['wardNo'] = ($d->ward_no) ? $d->ward_no : $d->apt_ward_no;
                $val['address'] = ($d->address) ? $d->address : $d->apt_address;
                $val['printedBy'] = $this->GetUserDetails($d->printed_by)->name ?? "";
                $val['printDateTime'] = date('d-m-Y h:i A', strtotime($d->print_datetime));
                $val['amount'] = $d->amount;
                $response[] = $val;
            }

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }
}
