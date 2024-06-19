<?php

namespace App\Repository;

use App\Models\Consumer;
use App\Models\ConsumerDeactivateDeatils;
use App\Models\Transaction;
use App\Models\Collections;
use App\Models\ConsumerEditLog;
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
    }
    #Arshad  
    public function ReportData(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            $response = array();
            if (isset($request->fromDate) && isset($request->toDate) && isset($request->reportType)) {
                $response = array();
                // if ($request->reportType == 'dailyCollection')
                //     $response = $this->DailyCollection($request->fromDate, $request->toDate, $request->wardNo, $request->consumerCategory, $request->consumerType, $request->apartmentId, $request->mode);

                //changed by talib
                if ($request->reportType == 'dailyCollection')
                    $response = $this->DailyCollection($request->fromDate, $request->toDate, $request->tcId, $request->wardNo, $request->consumerCategory, $request->consumerType, $request->apartmentId, $request->mode, $ulbId);
                // changed by talib

                if ($request->reportType == 'conAdd')
                    $response = $this->ConsumerAdd($request->fromDate, $request->toDate, $ulbId);

                if ($request->reportType == 'conDect')
                    $response = $this->ConsumerDect($request->fromDate, $request->toDate, $ulbId);

                if ($request->reportType == 'tranDect')
                    $response = $this->TransactionDeactivate($request->fromDate, $request->toDate, $request->tcId, $ulbId);

                if ($request->reportType == 'cashVeri')
                    $response = $this->CashVerification($request->fromDate, $request->toDate, $request->tcId, $ulbId);

                if ($request->reportType == 'bankRec')
                    $response = $this->BankReconcilliation($request->fromDate, $request->toDate, $request->tcId, $ulbId);

                if ($request->reportType == 'tcDaily')
                    $response = $this->TcDailyActivity($request->fromDate, $request->toDate, $request->tcId, $ulbId);

                if ($request->reportType == 'tranModeChange')
                    $response = $this->TransactionModeChange($request->fromDate, $request->toDate, $request->tcId, $ulbId);

                if ($request->reportType == 'consumereditlog')
                    $response = $this->consumerEditLog($request->fromDate, $request->toDate, $request->tcId, $ulbId);

                if ($request->reportType == 'monthlyComparison')
                    $response = $this->monthlyComparison($request->fromMonth, $request->wardNo);

                return response()->json(['status' => True, 'data' => ["details" => $response], 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    // public function DailyCollection($From, $Upto, $wardNo = null, $consumerCategory = null, $consumertype = null, $apartmentId = null, $mode = null)
    // {
    public function DailyCollection($From, $Upto, $tcId = null, $wardNo = null, $consumerCategory = null, $consumertype = null, $apartmentId = null, $mode = null, $ulbId)
    {

        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');

        $allTrans = $this->Transaction->select('swm_transactions.*', 'swm_consumers.ward_no', 'consumer_no', 'name', 'a.apt_code', 'a.apt_name')
            ->leftjoin('swm_consumers', 'swm_transactions.consumer_id', '=', 'swm_consumers.id')
            ->leftjoin('swm_apartments as a', 'swm_transactions.apartment_id', '=', 'a.id')
            ->leftjoin('swm_transaction_deactivates as td', 'td.transaction_id', '=', 'swm_transactions.id')
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
            $val['transactionNo'] = $trans->transaction_no;
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

    public function ConsumerAdd($From, $Upto, $ulbId)
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
        foreach ($consumers as $consumer) {
            $user = $this->GetUserDetails($consumer->user_id);
            $val['entryDate'] = Carbon::create($consumer->entry_date)->format('d-m-Y');
            $val['consumerNo'] = $consumer->consumer_no;
            $val['consumerName'] = $consumer->name;
            $val['consumerMobile'] = $consumer->mobile_no;
            $val['entryBy'] = ($user) ? $user->name : "";;
            $response[] = $val;
        }
        return $response;
    }

    public function ConsumerDect($From, $Upto, $ulbId)
    {
        $response = array();
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');

        $consumers = $this->ConsumerDeactivateDeatils->latest('id')
            ->select('swm_consumer_deactivates.*', 'name', 'consumer_no', 'mobile_no')
            ->join('swm_consumers', 'swm_consumer_deactivates.consumer_id', '=', 'swm_consumers.id')
            ->where('swm_consumer_deactivates.ulb_id', $ulbId)
            ->whereBetween('deactivation_date', [$From, $Upto])
            ->orderBy('swm_consumer_deactivates.id', 'desc')
            ->paginate(1000);

        foreach ($consumers as $consumer) {
            $user = $this->GetUserDetails($consumer->deactivated_by);
            $val['deactivateDate'] = Carbon::create($consumer->deactivation_date)->format('d-m-Y');
            $val['consumerNo'] = $consumer->consumer_no;
            $val['consumerName'] = $consumer->name;
            $val['consumerMobile'] = $consumer->mobile_no;
            $val['deactivateBy'] = ($user) ? $user->name : "";
            $val['remarks'] = $consumer->remarks;
            $response[] = $val;
        }
        return $response;
    }

    public function TransactionDeactivate($From, $Upto, $tcId = null, $ulbId)
    {
        $response = array();
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');


        $transaction = $this->TransactionDeactivate->latest('id')
            ->select('swm_transaction_deactivates.*', 'transaction_date', 'total_payable_amt', 'swm_transactions.user_id as transby', 'name', 'consumer_no', 'swm_transactions.payment_mode', 'a.apt_code', 'a.apt_name')
            ->join('swm_transactions', 'swm_transaction_deactivates.transaction_id', '=', 'swm_transactions.id')
            ->leftjoin('swm_consumers', 'swm_transactions.consumer_id', '=', 'swm_consumers.id')
            ->leftjoin('swm_apartments as a', 'swm_transactions.apartment_id', '=', 'a.id')
            ->where('swm_transactions.ulb_id', $ulbId);
        if (isset($tcId))
            $transaction = $transaction->where('swm_transactions.user_id', $tcId);
        $transaction = $transaction->whereBetween('date', [$From, $Upto])
            ->paginate(1000);

        foreach ($transaction as $trans) {
            $val['deactivateDate'] = Carbon::create($trans->date)->format('d-m-Y');
            $val['transactionDate'] = Carbon::create($trans->transaction_date)->format('d-m-Y');
            $val['amount'] = $trans->total_payable_amt;
            $val['transactionBy'] = $this->GetUserDetails($trans->transby)->name;
            $val['consumerName'] = $trans->name;
            $val['consumerNo'] = $trans->consumer_no;
            $val['apartmentName'] = $trans->apt_name;
            $val['apartmentCode'] = $trans->apt_code;
            $val['transactionMode'] = $trans->payment_mode;
            $val['deactivateBy'] = $this->GetUserDetails($trans->user_id)->name;
            $val['remarks'] = $trans->remarks;
            $response[] = $val;
        }
        return $response;
    }

    public function CashVerification($From, $Upto, $tcId = null, $ulbId)
    {
        $response = array();
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->addHours(24)->format('Y-m-d');


        $transaction = $this->TransactionVerification->latest('id')
            ->select('swm_transaction_verifications.*', 'transaction_date', 'total_payable_amt', 'swm_transactions.user_id as transby', 'name', 'consumer_no', 'swm_transactions.payment_mode', 'a.apt_code', 'a.apt_name')
            ->join('swm_transactions', 'swm_transaction_verifications.transaction_id', '=', 'swm_transactions.id')
            ->leftjoin('swm_consumers', 'swm_transactions.consumer_id', '=', 'swm_consumers.id')
            ->leftjoin('swm_apartments as a', 'swm_transactions.apartment_id', '=', 'a.id')
            ->where('swm_transactions.ulb_id', $ulbId);
        if (isset($tcId))
            $transaction = $transaction->where('swm_transactions.user_id', $tcId);
        $transaction = $transaction->whereBetween('verify_date', [$From, $Upto])
            ->paginate(1000);

        foreach ($transaction as $trans) {
            $val['verifiedDate'] = Carbon::create($trans->verify_date)->format('d-m-Y');
            $val['transactionDate'] = Carbon::create($trans->transaction_date)->format('d-m-Y');
            $val['amount'] = $trans->amount;
            $val['transactionBy'] = $this->GetUserDetails($trans->transby)->name;
            $val['consumerName'] = $trans->name;
            $val['consumerNo'] = $trans->consumer_no;
            $val['apartmentName'] = $trans->apt_name;
            $val['apartmentCode'] = $trans->apt_code;
            $val['transactionMode'] = $trans->payment_mode;
            $val['verifiedBy'] = $this->GetUserDetails($trans->verify_by)->name;
            $val['remarks'] = $trans->remarks;
            $response[] = $val;
        }
        return $response;
    }

    public function BankReconcilliation($From, $Upto, $tcId = null, $ulbId)
    {
        $response = array();
        $From = Carbon::create($From)->format('Y-m-d');
        $Upto = Carbon::create($Upto)->format('Y-m-d');

        $sql = "SELECT reconcile_id,reconcilition_date,t.transaction_no,transaction_date,t.payment_mode,cheque_dd_no, cheque_dd_date, bank_name,branch_name, total_payable_amt,bc.remarks,t.user_id as transby, name, consumer_no, a.apt_code, a.apt_name, bc.user_id as verify_by
            FROM  swm_transactions t
            JOIN swm_bank_reconcile bc on bc.transaction_id=t.id
            LEFT JOIN swm_consumers c on t.consumer_id=c.id
            LEFT JOIN swm_apartments a on t.apartment_id=a.id
            LEFT JOIN swm_bank_reconcile_details bd on bd.reconcile_id=bc.id
            LEFT JOIN swm_transaction_details td on td.transaction_id=t.id
            WHERE (transaction_date BETWEEN '$From' and '$Upto') and t.paid_status>0 and t.ulb_id=" . $ulbId;

        $transactions = DB::connection($this->dbConn)->select($sql);

        foreach ($transactions as $trans) {
            $val['clearanceDate'] = ($trans->reconcilition_date) ? Carbon::create($trans->reconcilition_date)->format('d-m-Y') : '';
            $val['amount'] = $trans->total_payable_amt;
            $val['transactionNo'] = $trans->transaction_no;
            $val['transactionDate'] = Carbon::create($trans->transaction_date)->format('d-m-Y');
            $val['transactionBy'] = $this->GetUserDetails($trans->transby)->name;
            $val['consumerName'] = $trans->name;
            $val['consumerNo'] = $trans->consumer_no;
            $val['apartmentName'] = $trans->apt_name;
            $val['apartmentCode'] = $trans->apt_code;
            $val['transactionMode'] = $trans->payment_mode;
            $val['chequeNo'] = $trans->cheque_dd_no;
            $val['chequeDate'] = ($trans->cheque_dd_date) ? Carbon::create($trans->cheque_dd_date)->format('d-m-Y') : '';
            $val['bankName'] = $trans->bank_name;
            $val['branchName'] = $trans->branch_name;
            $val['verifiedBy'] = $this->GetUserDetails($trans->verify_by)->name;
            $val['remarks'] = $trans->remarks;
            $response[] = $val;
        }
        return $response;
    }

    public function TcDailyActivity($From, $Upto, $tcId, $ulbId)
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

    public function TransactionModeChange($From, $Upto, $tcId = null, $ulbId)
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

    public function consumerEditLog($From, $Upto, $tcId = null, $ulbId)
    {
        $response = array();
        $mchange = $this->mConsumerEditLog
            ->select('swm_log_consumers.id', 'swm_consumers.consumer_no', 'swm_consumers.ward_no', 'swm_consumers.name', 'swm_consumers.mobile_no', 'swm_consumers.address', 'swm_consumers.pincode', 'swm_log_consumers.stampdate', 'swm_log_consumers.user_id')
            ->join('swm_consumers', 'swm_consumers.id', 'swm_log_consumers.consumer_id')
            ->whereBetween('swm_log_consumers.stampdate', [$From . " 00:00:01", $Upto . " 23:59:59"]);


        if (isset($tcId))
            $mchange = $mchange->where('swm_log_consumers.user_id', $tcId);

        $mchange = $mchange->get();

        foreach ($mchange as $detail) {
            $val['id']          = $detail->id;
            $val['consumer_no'] = $detail->consumer_no;
            $val['ward_no']     = $detail->ward_no;
            $val['mobile_no']   = $detail->mobile_no;
            $val['address']     = $detail->address;
            $val['pincode']     = $detail->pincode;
            $val['changeBy']    = $this->GetUserDetails($detail->user_id)->name;
            $val['changedDate'] = Carbon::create($detail->stampdate)->format('d-m-Y h:i A');
            $response[] = $val;
        }

        return $response;
    }

    public function monthlyComparison($fromMonth, $wardNo)
    {
        $wardNo = $wardNo ?? 1;
        $currentMonth = Carbon::now()->format('m');
        $currentYear  = Carbon::now()->format('Y');
        $response = array();

        return  $consumerDtls = $this->Consumer
            ->select(
                'swm_consumers.id as consumer_id ',
                'swm_demands.id as demand_id',
                'ward_no',
                'consumer_no',
                'name',
                'total_tax',
                'payment_from',
                'paid_status'
            )
            // ->where('swm_consumers.ward_no', $wardNo)
            ->join('swm_demands', 'swm_demands.consumer_id', 'swm_consumers.id')
            ->where('swm_consumers.id', 82063)
            ->where('swm_demands.is_deactivate', 0)
            ->whereDate('payment_from', '>=', Carbon::now()->subMonths(3)->startOfMonth())
            // ->whereMonth('payment_from', '>=',  $currentMonth)
            // ->whereYear('payment_from', $currentYear)
            ->orderByDesc('swm_demands.id')
            ->get();

        foreach ($consumerDtls as $consumer) {
            $tranDtls = $this->Transaction
                ->select(
                    DB::raw('EXTRACT (YEAR from transaction_date) as year'),
                    DB::raw('EXTRACT (MONTH from transaction_date) as month'),
                    DB::raw('TO_CHAR(transaction_date, \'Month\') as month_name'),
                    DB::raw('SUM(total_payable_amt) as value'),
                    DB::raw('SUM(CASE WHEN paid_status = 1 THEN total_payable_amt ELSE 0 END) as total_collection'),
                )
                ->where('consumer_id', $consumer->id)
                ->groupBy(
                    DB::raw('EXTRACT (YEAR from transaction_date)'),
                    DB::raw('EXTRACT (MONTH from transaction_date)'),
                    DB::raw('TO_CHAR(transaction_date, \'Month\')')
                )
                ->get();

            $val['consumer_id']           = $consumer->id;
            $val['consumer_ward_no']      = $consumer->ward_no;
            $val['consumer_consumer_no']  = $consumer->consumer_no;
            $val['consumer_name']         = $consumer->name;
            $val['transactionsDtls']      = $tranDtls;
            $transactions[]               = $val;
        }
        return $transactions;





        return $response;
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
}
