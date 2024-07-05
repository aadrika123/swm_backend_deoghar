<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Collections;
use App\Models\Consumer;
use App\Models\ConsumerType;
use App\Models\Demand;
use App\Models\OtpRequest;
use App\Models\RazorpayReq;
use App\Models\RazorpayResponse;
use App\Models\TblUserMstr;
use App\Models\TcComplaint;
use App\Models\Transaction;
use App\Models\Ward;
use App\Repository\ConsumerRepository;
use App\Repository\iMasterRepository;
use App\Repository\MasterRepository;
use App\Traits\Api\Helpers;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Razorpay\Api\Api;

use function App\Traits\Api\responseMsgs;

class CitizenController extends Controller
{
    use Helpers;

    protected $dbConn;
    protected $mConsumer;
    protected $mWard;
    protected $mDemand;
    protected $mTransaction;
    protected $mCollections;
    protected $mApartment;
    protected $mConsumerType;
    protected $mTcComplaint;

    public function __construct(Request $request)
    {
        $this->dbConn = "db_swm";

        $this->mWard     = new Ward($this->dbConn);
        $this->mConsumer = new Consumer($this->dbConn);
        $this->mDemand   = new Demand($this->dbConn);
        $this->mTransaction  = new Transaction($this->dbConn);
        $this->mCollections  = new Collections($this->dbConn);
        $this->mApartment    = new Apartment($this->dbConn);
        $this->mConsumerType = new ConsumerType($this->dbConn);
        $this->mTcComplaint   = new TcComplaint($this->dbConn);
        // $this->ConsumerType = new ConsumerType($this->dbConn);
        // $this->ConsumerCategory = new ConsumerCategory($this->dbConn);
        // $this->ConsumerDeactivateDeatils = new ConsumerDeactivateDeatils($this->dbConn);
        // $this->TransactionDetails = new TransactionDetails($this->dbConn);
        // $this->TransactionDeactivate = new TransactionDeactivate($this->dbConn);
        // $this->GeoLocation = new GeoLocation($this->dbConn);
        // $this->CosumerReminder = new CosumerReminder($this->dbConn);
        // $this->TransactionVerification = new TransactionVerification($this->dbConn);
        // $this->BankCancel = new BankCancel($this->dbConn);
        // $this->BankCancelDetails = new BankCancelDetails($this->dbConn);
        // $this->PaymentDeny = new PaymentDeny($this->dbConn);
        // $this->TransactionModeChange = new TransactionModeChange($this->dbConn);
        // $this->ConsumerEditLog = new ConsumerEditLog($this->dbConn);
        // $this->DemandLog = new DemandLog($this->dbConn);
        // $this->DemandAdjustment = new DemandAdjustment($this->dbConn);
        // $this->TcComplaint = new TcComplaint($this->dbConn);
        // $this->Routes = new Routes($this->dbConn);
    }

    /**
     * | Ward List
     */
    public function wardList(Request $request)
    {
        try {
            $ulbId    = "11";
            $wardList = $this->mWard;
            $wardList = $wardList->where('ulb_id', $ulbId)->orderBy('sqorder')->get();

            return $this->responseMsgs(true, "Ward List", $wardList);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | List of Residential Consumers
     */
    public function residentialConsumers(Request $request)
    {
        try {
            $perPage = $request->perPage ?? 10;
            $data    = $this->mConsumer
                ->select('swm_consumers.id', 'ward_no', 'swm_consumers.name', 'mobile_no', 'address', 'swm_consumer_types.name as consumer_type')
                ->join('swm_consumer_types', 'swm_consumer_types.id', 'swm_consumers.consumer_type_id')
                ->where('consumer_category_id', 1)
                ->where('is_deactivate', 0);

            if (request()->has('consumerNo'))
                $data->where('consumer_no', request()->input('consumerNo'));

            if (request()->has('consumerName'))
                $data->where('swm_consumers.name', 'like', '%' . request()->input('consumerName') . '%');

            if (request()->has('mobileNo'))
                $data->where('mobile_no', request()->input('mobileNo'));

            $data = $data->paginate($perPage);
            $newData['data'] = $this->getDemandByConsumer($data);
            $newData['total'] = $data->total();
            $newData['last_page'] = $data->lastPage();
            $newData['current_page'] = $data->currentPage();
            $newData['per_page'] = $data->perPage();
            return $this->responseMsgs(true, "Residential Consumer", $newData);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | List of Commercial Consumers
     */
    public function commercialConsumers(Request $request)
    {
        try {
            $perPage = $request->perPage ?? 10;
            $data    = $this->mConsumer
                ->select('swm_consumers.id', 'ward_no', 'swm_consumers.name', 'mobile_no', 'address', 'swm_consumer_types.name as consumer_type')
                ->join('swm_consumer_types', 'swm_consumer_types.id', 'swm_consumers.consumer_type_id')
                ->where('consumer_category_id', '<>', 1)
                ->where('is_deactivate', 0);

            if (request()->has('consumerNo'))
                $data->where('consumer_no', request()->input('consumerNo'));

            if (request()->has('consumerName'))
                $data->where('swm_consumers.name', 'like', '%' . request()->input('consumerName') . '%');

            if (request()->has('mobileNo'))
                $data->where('mobile_no', request()->input('mobileNo'));

            $data = $data->paginate($perPage);
            $newData['data'] = $this->getDemandByConsumer($data);
            $newData['total'] = $data->total();
            $newData['last_page'] = $data->lastPage();
            $newData['current_page'] = $data->currentPage();
            $newData['per_page'] = $data->perPage();
            return $this->responseMsgs(true, "Commercial Consumer", $newData);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | Get Demand By Consumer 2.1 && 3.1
     */
    public function getDemandByConsumer($consumerList)
    {
        foreach ($consumerList as $consumer) {
            $demand = $this->mDemand->where('consumer_id', $consumer->id)
                // ->where('ulb_id', $ulbId)
                ->where('paid_status', 0)
                ->where('is_deactivate', 0)
                ->orderBy('id', 'asc')
                ->get();
            $total_tax = 0.00;
            $demand_upto = '';
            $paid_status = 'true';
            foreach ($demand as $dmd) {
                $total_tax += $dmd->total_tax;
                $demand_upto = $dmd->demand_date;
                $paid_status = 'false';
            }

            $con['id'] = $consumer->id;
            $con['name'] = $consumer->name;
            $con['ward_no'] = $consumer->ward_no;
            $con['consumer_no'] = $consumer->consumer_no;
            $con['address'] = $consumer->address;
            $con['consumer_type'] = $consumer->consumer_type;
            $con['mobile_no'] = $consumer->mobile_no;
            $con['total_demand'] = $total_tax;
            $con['demand_upto'] = $demand_upto;
            $con['paid_status'] = $paid_status;
            // $con['demand_details'] = $demand;
            $conArr[] = $con;
        }
        return $conArr;
    }

    /**
     * | Get Consumer Details
     */
    public function consumerDtl(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ["id" => "required|integer"]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {
            $transactions = array();
            $consumer = $this->mConsumer
                ->select('swm_consumers.id', 'ward_no', 'swm_consumers.name', 'consumer_no', 'mobile_no', 'address', 'swm_consumer_types.name as consumer_type', 'swm_consumer_categories.name as consumer_category')
                ->join('swm_consumer_types', 'swm_consumer_types.id', 'swm_consumers.consumer_type_id')
                ->join('swm_consumer_categories',  'swm_consumer_categories.id', 'swm_consumers.consumer_category_id')
                // ->where('consumer_category_id', 1)
                ->where('is_deactivate', 0)
                ->where('swm_consumers.id', $request->id)
                ->first();

            $demand = $this->mDemand->where('consumer_id', $consumer->id)
                ->where('paid_status', 0)
                ->where('is_deactivate', 0)
                // ->where('ulb_id', $ulbId)
                ->orderBy('id', 'asc')
                ->get();
            $total_tax = 0.00;
            $demand_upto = '';
            $paid_status = 'Paid';
            $monthlyDemand = 0;
            $demand_from = '';
            $i = 0;

            foreach ($demand as $dmd) {
                if ($i == 0)
                    $demand_from = date('d-m-Y', strtotime($dmd->payment_from));
                $i++;
                $demand_upto = date('d-m-Y', strtotime($dmd->payment_to));
                $monthlyDemand = $dmd->total_tax;
                $total_tax += $dmd->total_tax;
                $paid_status = 'Unpaid';
            }

            $tranDtls = $this->mTransaction->select('id', 'transaction_no', 'transaction_date', 'payment_mode', 'total_payable_amt', 'user_id');

            if (isset($consumer->id))
                $tranDtls = $tranDtls
                    ->where('swm_transactions.consumer_id', $consumer->id);

            if (isset($request->apartmentId))
                $tranDtls = $tranDtls
                    ->where('swm_transactions.apartment_id', $request->apartmentId);

            $tranDtls = $tranDtls->orderBy('swm_transactions.id', 'desc')->take(10)->get();
            foreach ($tranDtls as $trans) {
                $collection = $this->mCollections->where('transaction_id', $trans->id);
                $firstrecord = $collection->orderBy('id', 'asc')->first();
                $lastrecord = $collection->latest('id')->first();
                $getuserdata = $this->GetUserDetails($trans->user_id);

                $val['transaction_id']    = $trans->id;
                $val['transaction_no']    = $trans->transaction_no;
                $val['payment_mode']      = $trans->payment_mode;
                $val['transaction_date']  = Carbon::create($trans->transaction_date)->format('d-m-Y');
                $val['total_payable_amt'] = $trans->total_payable_amt;
                $val['demand_from']       = ($firstrecord) ? Carbon::create($firstrecord->payment_from)->format('Y-m-d') : '';
                $val['demand_upto']       = ($lastrecord) ? Carbon::create($lastrecord->payment_to)->format('Y-m-d') : '';
                $val['tc_name']           = $getuserdata->name ?? "";
                $transactions[]           = $val;
            }

            $con['id'] = $consumer->id;
            $con['ward_no'] = $consumer->ward_no;
            $con['name'] = $consumer->name;
            $con['apartment_id'] = $consumer->apartment_id;
            $con['consumer_no'] = $consumer->consumer_no;
            $con['holding_no'] = $consumer->holding_no;
            $con['address'] = $consumer->address;
            $con['consumer_category'] = $consumer->consumer_category;
            $con['consumer_type'] = $consumer->consumer_type;
            $con['mobile_no'] = $consumer->mobile_no;
            $con['monthly_demand'] = $monthlyDemand;
            $con['total_demand'] = $total_tax;
            $con['demand_from'] = $demand_from;
            $con['demand_upto'] = $demand_upto;
            $con['paid_status'] = $paid_status;
            $con['demand_details'] = $demand;
            $con['transaction_details'] = $transactions;
            return $this->responseMsgs(true, "Consumer Details", $con);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | Get Consumer Details Version 2
     */
    public function consumerDtlV2(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ["id" => "required|integer"]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {
            $transactions = array();
            $consumer = $this->mConsumer
                ->select('swm_consumers.id', 'ward_no', 'swm_consumers.name', 'consumer_no', 'mobile_no', 'address', 'swm_consumer_types.name as consumer_type', 'swm_consumer_categories.name as consumer_category', 'owner_id')
                ->join('swm_consumer_types', 'swm_consumer_types.id', 'swm_consumers.consumer_type_id')
                ->join('swm_consumer_categories',  'swm_consumer_categories.id', 'swm_consumers.consumer_category_id')
                ->where('is_deactivate', 0)
                ->where('swm_consumers.id', $request->id)
                ->first();

            $renter = $this->mConsumer
                ->select('swm_consumers.id', 'ward_no', 'swm_consumers.name', 'consumer_no', 'mobile_no', 'address', 'swm_consumer_types.name as consumer_type', 'swm_consumer_categories.name as consumer_category', 'owner_id')
                ->join('swm_consumer_types', 'swm_consumer_types.id', 'swm_consumers.consumer_type_id')
                ->join('swm_consumer_categories',  'swm_consumer_categories.id', 'swm_consumers.consumer_category_id')
                ->where('is_deactivate', 0)
                ->where('owner_id', $consumer->id)
                ->get();

            $renterIds = collect($renter)->pluck('id');
            $consumerId = [$consumer->id];
            $consumerIds = array_merge($consumerId, $renterIds->all());

            foreach ($consumerIds as $consumerId) {

                $demand = $this->mDemand->where('consumer_id', $consumerId)
                    ->where('paid_status', 0)
                    ->where('is_deactivate', 0)
                    // ->where('ulb_id', $ulbId)
                    ->orderBy('id', 'asc')
                    ->get();

                $total_tax = 0.00;
                $demand_upto = '';
                $paid_status = 'Paid';
                $monthlyDemand = 0;
                $demand_from = '';
                $i = 0;

                foreach ($demand as $dmd) {
                    if ($i == 0)
                        $demand_from = date('d-m-Y', strtotime($dmd->payment_from));
                    $i++;
                    $demand_upto = date('d-m-Y', strtotime($dmd->payment_to));
                    $monthlyDemand = $dmd->total_tax;
                    $total_tax += $dmd->total_tax;
                    $paid_status = 'Unpaid';
                }

                $con['id'] = $consumer->id;
                $con['consumer_id'] = $consumer->id;
                $con['consumer_name'] = $consumer->name;
                $con['consumer_no'] = $consumer->consumer_no;
                $con['holding_no'] = $consumer->holding_no;
                $con['mobile_no'] = $consumer->mobile_no;
                $con['pincode'] = $consumer->pincode;
                $con['demand_details'] = $demand;
                $con['monthly_demand'] = $monthlyDemand;
                $con['total_demand'] = $total_tax;
                $con['demand_from'] = $demand_from;
                $con['demand_upto'] = $demand_upto;
                $con['paid_status'] = $paid_status;
                // $con['applyBy'] = ($apartment->user_id) ? $this->GetUserDetails($apartment->user_id)->name : '';
                // $con['applyDate'] = ($apartment->entry_date) ? date("d-m-Y", strtotime($apartment->entry_date)) : '';
                // $con['editApplicable'] = ($trans == 0) ? true : false;

                $consumerArr[] = $con;
            }
            return $consumerArr;

            $tranDtls = $this->mTransaction->select('id', 'transaction_no', 'transaction_date', 'payment_mode', 'total_payable_amt', 'user_id');

            if (isset($consumer->id))
                $tranDtls = $tranDtls
                    ->where('swm_transactions.consumer_id', $consumer->id);

            $tranDtls = $tranDtls->orderBy('swm_transactions.id', 'desc')->take(10)->get();
            foreach ($tranDtls as $trans) {
                $collection = $this->mCollections->where('transaction_id', $trans->id);
                $firstrecord = $collection->orderBy('id', 'asc')->first();
                $lastrecord = $collection->latest('id')->first();
                $getuserdata = $this->GetUserDetails($trans->user_id);

                $val['transaction_no']    = $trans->transaction_no;
                $val['payment_mode']      = $trans->payment_mode;
                $val['transaction_date']  = Carbon::create($trans->transaction_date)->format('d-m-Y');
                $val['total_payable_amt'] = $trans->total_payable_amt;
                $val['demand_from']       = ($firstrecord) ? Carbon::create($firstrecord->payment_from)->format('Y-m-d') : '';
                $val['demand_upto']       = ($lastrecord) ? Carbon::create($lastrecord->payment_to)->format('Y-m-d') : '';
                $val['tc_name']           = $getuserdata->name ?? "";
                $transactions[]           = $val;
            }

            $con['id'] = $consumer->id;
            $con['ward_no'] = $consumer->ward_no;
            $con['name'] = $consumer->name;
            $con['apartment_id'] = $consumer->apartment_id;
            $con['consumer_no'] = $consumer->consumer_no;
            $con['holding_no'] = $consumer->holding_no;
            $con['address'] = $consumer->address;
            $con['consumer_category'] = $consumer->consumer_category;
            $con['consumer_type'] = $consumer->consumer_type;
            $con['mobile_no'] = $consumer->mobile_no;
            $con['monthly_demand'] = $monthlyDemand;
            $con['total_demand'] = $total_tax;
            $con['demand_from'] = $demand_from;
            $con['demand_upto'] = $demand_upto;
            $con['paid_status'] = $paid_status;
            $con['demand_details'] = $demand;
            $con['transaction_details'] = $transactions;
            return $this->responseMsgs(true, "Consumer Details", $con);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | Get Payment Upto
     */
    public function paymentUpto(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ["consumerId" => "required|integer"]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {
            $demand = $this->mDemand->select('payment_to');
            if (isset($request->consumerId) && $request->consumerType != 'apartment')
                $demand = $demand->where('consumer_id', $request->consumerId);
            if (isset($request->consumerId) && $request->consumerType == 'apartment')
                $demand = $demand->join('swm_consumers as a', 'swm_demands.consumer_id', '=', 'a.id')
                    ->where('a.apartment_id', $request->consumerId);
            $demand = $demand->where('paid_status', 0)
                ->where('swm_demands.is_deactivate', 0)
                // ->where('swm_demands.ulb_id', $ulbId)
                ->groupBy('payment_to')
                ->orderBy('payment_to', 'asc')
                ->get();

            return $this->responseMsgs(true, "Payment Upto Data", $demand);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | Apartment List
     */
    public function apartmentList(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ["wardNo" => "required|integer"]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {
            $apartmentList = $this->mApartment->where('ward_no', $request->wardNo)->get();

            return $this->responseMsgs(true, "List of Apartments", $apartmentList);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | Apartment Detail
     */
    public function apartmentDtl(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ["id" => "required|integer"]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {
            $ulbId = 11;
            $perPage = $request->perPage ?? 10;
            $apartmentDtls = $this->mApartment->where('swm_apartments.id', $request->id)
                ->where('swm_apartments.is_deactivate', 0)
                ->first();
            // ->paginate($perPage);


            $demand = $this->GetDemand($this->dbConn, $apartmentDtls->id, 'Apartment', $ulbId);

            $data = $apartmentDtls;
            $data->demandAmt = $demand['demandAmt'];
            $data->demandUpto = $demand['demandUpto'];

            $response['data'] = array($data);
            $response['current_page'] = 1;
            $response['last_page'] = 1;
            $response['total'] = 1;
            $response['per_page'] = $perPage;

            return $this->responseMsgs(true, "Apartment Details", $response);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | Apartment Detail by id
     */
    public function apartmentDtlById(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ["id" => "required|integer"]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {
            $ulbId = 11;

            $apartmentDtls = $this->mApartment->where('swm_apartments.id', $request->id)
                // ->where('swm_apartments.is_deactivate', 0)
                ->first();

            $consumerDtls = $this->mConsumer->where('apartment_id', $request->id)->get();

            $apt_tot_tax = 0;
            $aptmonthlyDemand = 0;
            foreach ($consumerDtls as $consumer) {

                $demand = $this->mDemand->where('consumer_id', $consumer->id)
                    ->where('paid_status', 0)
                    ->where('is_deactivate', 0)
                    ->where('ulb_id', $ulbId)
                    ->get();

                $total_tax = 0.00;
                $demand_upto = '';
                $paid_status = 'Paid';
                $monthlyDemand = 0;
                $demand_from = '';
                $i = 0;
                if (collect($demand)->isNotEmpty()) {
                    foreach ($demand as $dmd) {
                        if ($i == 0)
                            $demand_from = date('d-m-Y', strtotime($dmd->payment_from));
                        $i++;
                        $total_tax += $dmd->total_tax;
                        $demand_upto = date('d-m-Y', strtotime($dmd->payment_to));
                        $paid_status = 'Unpaid';
                        $monthlyDemand = $dmd->total_tax;
                    }
                }

                $apt_tot_tax += $total_tax;
                $aptmonthlyDemand += $monthlyDemand;

                $con['id'] = $consumer->id;
                $con['consumer_id'] = $consumer->id;
                $con['consumer_name'] = $consumer->name;
                $con['consumer_no'] = $consumer->consumer_no;
                $con['holding_no'] = $consumer->holding_no;
                $con['mobile_no'] = $consumer->mobile_no;
                $con['pincode'] = $consumer->pincode;
                $con['demand_details'] = $demand;
                $con['monthly_demand'] = $monthlyDemand;
                $con['total_demand'] = $total_tax;
                $con['demand_from'] = $demand_from;
                $con['demand_upto'] = $demand_upto;
                $con['paid_status'] = $paid_status;
                // $con['applyBy'] = ($apartment->user_id) ? $this->GetUserDetails($apartment->user_id)->name : '';
                // $con['applyDate'] = ($apartment->entry_date) ? date("d-m-Y", strtotime($apartment->entry_date)) : '';
                // $con['editApplicable'] = ($trans == 0) ? true : false;

                $consumerArr[] = $con;
            }

            $tranDtls = $this->mTransaction
                ->select('id', 'transaction_no', 'transaction_date', 'payment_mode', 'total_payable_amt', 'user_id')
                ->where('swm_transactions.apartment_id', $apartmentDtls->id)
                ->orderBy('swm_transactions.id', 'desc')
                ->take(10)
                ->get();
            foreach ($tranDtls as $trans) {
                $collection = $this->mCollections->where('transaction_id', $trans->id);
                $firstrecord = $collection->orderBy('id', 'asc')->first();
                $lastrecord = $collection->latest('id')->first();
                $getuserdata = $this->GetUserDetails($trans->user_id);

                $val['transaction_id']    = $trans->id;
                $val['transaction_no']    = $trans->transaction_no;
                $val['payment_mode']      = $trans->payment_mode;
                $val['transaction_date']  = Carbon::create($trans->transaction_date)->format('d-m-Y');
                $val['total_payable_amt'] = $trans->total_payable_amt;
                $val['demand_from']       = ($firstrecord) ? Carbon::create($firstrecord->payment_from)->format('Y-m-d') : '';
                $val['demand_upto']       = ($lastrecord) ? Carbon::create($lastrecord->payment_to)->format('Y-m-d') : '';
                $val['tc_name']           = $getuserdata->name ?? "";
                $transactions[]           = $val;
            }

            $data['id'] = $apartmentDtls->id;
            $data['ward_no'] = $apartmentDtls->ward_no;
            $data['apartment_name'] = $apartmentDtls->apt_name;
            $data['apartment_code'] = $apartmentDtls->apt_code;
            $data['address'] = $apartmentDtls->apt_address;
            $data['building_type'] = "Apartment";
            $data['apartment_monthly_demand'] = collect($consumerArr)->sum('monthly_demand');
            $data['apartment_total_demand'] = collect($consumerArr)->sum('total_demand');
            $data['consumerDtls'] = $consumerArr;
            $data['transaction_details'] = $transactions;

            return $this->responseMsgs(true, "Apartment Details By Id", $data);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | Calculate Amount
     */
    public function calculateAmount(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                "payUpto"      => "required|date",
                "consumerId"   => "required",
                "consumerType" => "required",
            ]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {
            $response = array();
            $consumerId   = $request->consumerId;
            $consumerType = $request->consumerType;
            $payUpto      = $request->payUpto;

            if (isset($consumerId) && isset($payUpto) && $consumerType != 'apartment') {

                $demand = $this->mDemand;

                if (isset($request->apartmentId)) {
                    $demand = $demand->join('swm_consumers as c', 'swm_demands.consumer_id', '=', 'c.id')
                        ->where('c.apartment_id', $request->apartmentId)
                        ->where('c.is_deactivate', 0);
                } else {
                    $demand = $demand->where('consumer_id', $consumerId);
                }
                $demand = $demand->where('paid_status', 0)
                    // ->where('swm_demands.ulb_id', $ulbId)
                    ->where('swm_demands.is_deactivate', 0)
                    ->whereDate('swm_demands.payment_to', '<=', $payUpto)
                    ->orderBy('swm_demands.id', 'asc')
                    ->sum('total_tax');

                $totalDmd = $demand;
                $paymentUptoDate = date('Y-m-t', strtotime($payUpto));

                $response['totaldemand'] = $totalDmd;
                $response['paymentUptoDate'] = $paymentUptoDate;
            }

            if (isset($consumerId) && isset($payUpto) && $consumerType == 'apartment') {

                $demand = $this->mDemand;
                $demand = $demand->join('swm_consumers as c', 'swm_demands.consumer_id', '=', 'c.id')
                    ->where('c.apartment_id', $consumerId)
                    ->where('c.is_deactivate', 0);

                $demand = $demand->where('paid_status', 0)
                    // ->where('swm_demands.ulb_id', $ulbId)
                    ->where('swm_demands.is_deactivate', 0)
                    ->whereDate('swm_demands.payment_to', '<=', $payUpto)
                    ->orderBy('swm_demands.id', 'asc')
                    ->sum('total_tax');

                $totalDmd = $demand;
                $paymentUptoDate = date('Y-m-t', strtotime($payUpto));

                $response['totaldemand'] = $totalDmd;
                $response['paymentUptoDate'] = $paymentUptoDate;
            }

            return $this->responseMsgs(true, "Total Demand", $response);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | Initiate Online Payment
     */
    public function initiatePayment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "amount"        => "required|numeric",
            "consumerId"    => "required|int",
            "consumerType"  => "nullable|in:commercial,independent,apartment",
            // "consumerType"  => "nullable|in:consumer,apartment",
        ]);

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);

        try {

            $apiId = "0701";
            $version = "01";
            $keyId        = Config::get('constants.RAZORPAY_KEY');
            $secret       = Config::get('constants.RAZORPAY_SECRET');
            $mRazorpayReq = new RazorpayReq();
            $api          = new Api($keyId, $secret);
            $consumerType = $req->consumerType;
            $apartmentId     = null;
            $consumerId      = null;

            if ($consumerType == 'apartment') {
                $consumerDetails = $this->mApartment->where('id', $req->consumerId)->first();
                $apartmentId     = $consumerDetails->id;
            } else {
                $consumerDetails = $this->mConsumer->where('id', $req->consumerId)->first();
                $consumerId      = $consumerDetails->id;
            }

            if (!$consumerDetails)
                throw new Exception("Consumer Not Found");
            // if ($penaltyDetails->payment_status == 1)
            //     throw new Exception("Payment Already Done");

            $orderData = $api->order->create(array('amount' => $req->amount * 100, 'currency' => 'INR',));

            $mReqs = [
                "order_id"       => $orderData['id'],
                "payment_type"   => $consumerType,
                "consumer_id"    => $consumerId,
                "apartment_id"   => $apartmentId,
                "user_id"        => 0,
                "amount"         => $req->amount,
                "ulb_id"         => $consumerDetails->ulb_id,
                "ip_address"     => $this->getClientIpAddress()
            ];
            $data = $mRazorpayReq->store($mReqs);
            $response = [
                'order_id'      => $orderData['id'],
                'consumer_id'   => $req->consumerId,
                'consumer_type' => $req->consumerType,
            ];

            return $this->responseMsgs(true, "Order Id Details", $response);
        } catch (Exception $e) {
            return $this->responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], "");
        }
    }

    /**
     * | Save Razor Pay Response
     */
    public function saveRazorpayResponse(Request $req)
    {
        try {
            $apiId = "0702";
            $version = "01";
            Storage::disk('public')->put($req->orderId . '.json', json_encode($req->all()));
            $mRazorpayReq        = new RazorpayReq();
            $mRazorpayResponse   = new RazorpayResponse();
            $todayDate           = Carbon::now();
            $consumerType        = $req->consumerType;
            $consumerRepo        = app(ConsumerRepository::class);

            if ($consumerType == 'apartment')
                $paymentData = $mRazorpayReq->getPaymentRecord($req)
                    ->where('apartment_id', $req->consumerId)
                    ->first();
            else
                $paymentData = $mRazorpayReq->getPaymentRecord($req)
                    ->where('consumer_id', $req->consumerId)
                    ->first();

            if (collect($paymentData)->isEmpty())
                throw new Exception("Payment Data not available");
            if ($paymentData) {
                $mReqs = [
                    "request_id"      => $paymentData->id,
                    "order_id"        => $req->orderId,
                    "merchant_id"     => $req->mid,
                    "payment_id"      => $req->paymentId,
                    "consumer_id"     => $req->consumerId,
                    "apartment_id"    => $req->apartmentId,
                    "amount"          => $req->amount,
                    "ulb_id"          => $paymentData->ulb_id,
                    "ip_address"      => $this->getClientIpAddress(),
                    // "res_ref_no"      => $transactionNo,                         // flag
                    // "response_msg"    => $pinelabData['Response']['ResponseMsg'],
                    // "response_code"   => $pinelabData['Response']['ResponseCode'],
                    // "description"     => $req->description,
                ];

                $data = $mRazorpayResponse->store($mReqs);
                $paymentData->payment_status = 1;
                $paymentData->save();

                if ($consumerType == 'apartment') {
                    $newReqs = new Request([
                        'apartmentId'  => $req->consumerId,
                        'paymentMode' => 'ONLINE',
                        'paidAmount'  => $req->amount,
                        'paidUpto'    => $req->payUpto,
                    ]);
                    $responseData = $consumerRepo->makeApartmentPayment($newReqs);
                } else {
                    $newReqs = new Request([
                        'consumerId'  => $req->consumerId,
                        'paidUpto'    => $req->payUpto,
                        'paidAmount'  => $req->amount,
                        'paymentMode' => 'ONLINE',
                    ]);
                    $responseData = $consumerRepo->makePayment($newReqs);
                }
            }


            // #_Whatsaap Message
            // if (strlen($penaltyDetails->mobile) == 10) {
            //     $whatsapp2 = (Whatsapp_Send(
            //         $penaltyDetails->mobile,
            //         "juidco_fines_payment",
            //         [
            //             "content_type" => "text",
            //             [
            //                 $penaltyDetails->full_name ?? "Violator",
            //                 $tranDtl->total_amount,
            //                 $challanDetails->challan_no,
            //                 $tranDtl->tran_no,
            //                 $ulbDtls->toll_free_no ?? 0000000000
            //             ]
            //         ]
            //     ));
            // }

            return $responseData;
            return $this->responseMsgs(true, "Data Saved", $responseData);
        } catch (Exception $e) {
            return $this->responseMsgs(false, $e->getMessage(), "");
        }
    }

    /**
     * | listConsumerType
     */
    public function listConsumerType(Request $req)
    {
        try {
            $categoryList = $this->mConsumerType->select(
                'swm_consumer_types.id',
                'swm_consumer_types.name as consumer_type',
                'swm_consumer_categories.name as consumer_category',
                'swm_consumer_types.rate'
            )
                ->join('swm_consumer_categories', 'swm_consumer_categories.id', 'swm_consumer_types.category_id')
                ->orderBy('swm_consumer_types.id')
                ->paginate();

            return $this->responseMsgs(true, "Consumer Type Rate Chart", $categoryList);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | listTaxCollector
     */
    public function listTaxCollector(Request $req)
    {
        $ulbId = 11;
        try {
            $tcDetails = TblUserMstr::select('tbl_user_mstr.id', 'tbl_user_details.name', 'tbl_user_details.contactno', 'tbl_user_ward.user_id', 'ward_id')
                ->join('tbl_user_details', 'tbl_user_details.id', 'tbl_user_mstr.user_det_id')
                ->join('tbl_user_ward', 'tbl_user_ward.user_id', 'tbl_user_mstr.id')
                ->where('tbl_user_mstr.user_type_id', 5)
                ->where('tbl_user_ward.ulb_id', $ulbId)
                ->where('tbl_user_mstr.status', 1)
                ->groupBy('tbl_user_ward.user_id', 'tbl_user_details.name', 'tbl_user_details.contactno', 'tbl_user_mstr.id', 'tbl_user_ward.ward_id')
                ->orderBy('tbl_user_ward.user_id')
                ->get();

            $response = $tcDetails->groupBy('user_id')->map(function ($group) {
                return [
                    'id' => $group->first()['id'],
                    'name' => $group->first()['name'],
                    'contactno' => $group->first()['contactno'],
                    'user_id' => $group->first()['user_id'],
                    'ward_ids' => implode(',', $group->pluck('ward_id')->all())
                ];
            })->values()->all();

            // Convert the array to a Laravel collection
            $collection = collect($response);

            // Get current page form url e.g. &page=1
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage     = $req->perPage ?? 10;
            $currentPageItems = $collection->slice(($currentPage - 1) * $perPage, $perPage)->values();

            // Create the paginator and pass it to the view
            $paginatedItems = new LengthAwarePaginator(
                $currentPageItems,
                $collection->count(),
                $perPage,
                $currentPage
            );

            return $this->responseMsgs(true, "List of tax collector", $paginatedItems);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | paymentReceipt
     */
    public function paymentReceipt(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                "transactionId"   => "required",
            ]
        );

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {
            $tranDtl      = $this->mTransaction->where('id', $req->transactionId)->first();
            $ulbId        = $tranDtl->ulb_id ?? 21;
            if (isset($req->transactionId)) {
                $transactionId = $req->transactionId;

                $sql = "SELECT t.transaction_no,t.transaction_date,c.ward_no,c.name,c.address,a.apt_name, a.apt_code, c.consumer_no, a.apt_address, a.ward_no as apt_ward, 
                t.total_payable_amt, cl.payment_from, cl.payment_to, t.payment_mode,td.bank_name, td.branch_name, td.cheque_dd_no, td.cheque_dd_date, 
                t.total_demand_amt, t.total_remaining_amt, t.stampdate, t.apartment_id, ct.rate,cc.name as consumer_category,t.user_id, c.holding_no,c.mobile_no,ct.name as consumer_type,c.license_no
                FROM swm_transactions t
                LEFT JOIN swm_consumers c on t.consumer_id=c.id
                LEFT JOIN swm_consumer_types ct on c.consumer_type_id=ct.id
                LEFT JOIN swm_consumer_categories cc on c.consumer_category_id=cc.id
                LEFT JOIN swm_apartments a on t.apartment_id=a.id
                JOIN (
                    SELECT min(payment_from) as payment_from, max(payment_to) as payment_to,
                    transaction_id 
                    FROM swm_collections 
                    GROUP BY transaction_id
                ) cl on cl.transaction_id=t.id 
                LEFT JOIN swm_transaction_details td on td.transaction_id=t.id
                WHERE t.id='" . $transactionId . "'";

                $transaction = DB::connection($this->dbConn)->select($sql);

                if ($transaction) {
                    $transaction = $transaction[0];
                    $consumerCount = 0;
                    $monthlyRate = $transaction->rate;
                    if ($transaction->apartment_id) {
                        $consumer = $this->mConsumer->join('swm_consumer_types as ct', 'ct.id', '=', 'swm_consumers.consumer_type_id')
                            ->where('apartment_id', $transaction->apartment_id)
                            ->where('ulb_id', $ulbId)
                            ->where('is_deactivate', 0);
                        $consumerCount = $consumer->count();
                        $monthlyRate = $consumer->sum('rate');
                    }
                    $getTc = $this->GetUserDetails($transaction->user_id);

                    $response['transactionDate']  = Carbon::create($transaction->transaction_date)->format('d-m-Y');
                    $response['transactionTime']  = Carbon::create($transaction->stampdate)->format('h:i A');
                    $response['transactionNo']    = $transaction->transaction_no;
                    $response['consumerName']     = $transaction->name;
                    $response['consumerNo']       = $transaction->consumer_no;
                    $response['mobileNo']         = $transaction->mobile_no;
                    $response['consumerCategory'] = ($transaction->consumer_category) ? $transaction->consumer_category : 'RESIDENTIAL';
                    $response['consumerType']     = $transaction->consumer_type;
                    $response['licenseNo']        = isset($transaction->license_no) ? $transaction->license_no : '';
                    $response['apartmentName']    = $transaction->apt_name;
                    $response['apartmentCode']    = $transaction->apt_code;
                    $response['ReceiptWard']      = ($transaction->apt_ward) ? $transaction->apt_ward : $transaction->ward_no;
                    $response['holdingNo']        = $transaction->holding_no;
                    $response['address']          = ($transaction->apt_address) ? $transaction->apt_address : $transaction->address;
                    $response['paidFrom']         = $transaction->payment_from;
                    $response['paidUpto']         = $transaction->payment_to;
                    $response['paymentMode']      = $transaction->payment_mode;
                    $response['bankName']         = $transaction->bank_name;
                    $response['branchName']       = $transaction->branch_name;
                    $response['chequeNo']         = $transaction->cheque_dd_no;
                    $response['chequeDate']       = $transaction->cheque_dd_date;
                    $response['noOfFlats']        = $consumerCount;
                    $response['monthlyRate']      = $monthlyRate;
                    $response['demandAmount']     = ($transaction->total_demand_amt) ? $transaction->total_demand_amt : 0;
                    $response['paidAmount']       = ($transaction->total_payable_amt) ? $transaction->total_payable_amt : 0;
                    $response['remainingAmount']  = ($transaction->total_remaining_amt) ? $transaction->total_remaining_amt : 0;
                    $response['tcName']           = $getTc->name ?? "";
                    $response['tcMobile']         = $getTc->contactno ?? "";
                    $response['isapartment']      = ($transaction->apartment_id) ? true : false;
                }
            }
            $printData = array_merge($response, $this->GetUlbData($ulbId));

            return $this->responseMsgs(true, "Payment Receipt", $printData);
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | Get the Consumer Details By Consumer No
     */
    public function consumerDetailByConsumerNo(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'consumerNo'     => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => False, 'msg' => $validator->messages()->first(), 'data' => ""]);
        }
        $ulbId = 11;
        try {
            $data = $this->mConsumer
                ->where('consumer_no', $req->consumerNo)
                ->where('is_deactivate', 0)
                ->where('ulb_id', $ulbId)
                ->first();

            if ($data)
                return $this->responseMsgs(true, "Consumer Details", $data);
            else
                return $this->responseMsgs(false, "No Data Found", "");
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    /**
     * | Post Citizen Complain
     */
    public function postCitizenComplain(Request $request)
    {
        try {
            // $userId = $request->user()->id;
            $ulbId = 11;
            $validator = Validator::make($request->all(), [
                'complain'     => 'required',
                'consumerWard' => 'required',
                'latitude'     => 'required',
                'longitude'    => 'required',
                // 'photo' => 'required|mimes:jpg,jpeg,png',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()->first(), 'data' => ""]);
            }

            $complain = $this->mTcComplaint;
            $complain->complain       =  $request->complain;
            $complain->ward_no        =  $request->consumerWard;
            $complain->consumer_no    =  $request->consumerNo;
            $complain->consumer_name  =  $request->consumerName;
            $complain->address        =  $request->consumerAddress;
            $complain->latitude       =  $request->latitude;
            $complain->longitude      =  $request->longitude;
            $complain->tc_remarks     =  $request->remarks;
            $complain->mobile_no      =  $request->mobileNo;

            if (!empty($request->photo)) {
                $randomNumber  = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
                $filePath = md5($randomNumber) . '.' . $request->photo->extension();
                $request->photo->move(public_path('uploads'), $filePath);

                $complain->tc_photo = $filePath;
            }
            $complain->complain_date  = Carbon::now();
            $complain->created_at     = Carbon::now();
            $complain->ulb_id         = $ulbId;
            $complain->complain_no    = $this->generateComplainNumber($request->consumerWard);
            $complain->save();


            #_Whatsaap Message
            if (strlen($complain->mobile_no) == 10) {
                $whatsapp2 = (Whatsapp_Send(
                    $complain->mobile_no,
                    "all_module_generate_complain",
                    [
                        "content_type" => "text",
                        [
                            $complain->consumer_name ?? "Citizen",
                            "SWM",
                            "Complain No.",
                            $complain->complain_no,
                            "Consumer No.",
                            $complain->consumer_no ?? "-",
                            "1800123123"
                        ]
                    ]
                ));
            }

            return response()->json(['status' => True, 'data' => $complain->complain_no, 'msg' => 'Your complain has been registered and complain no is ' . $complain->complain_no], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    /**
     * | Get Citizen Complain
     */
    public function getCitizenComplain(Request $request)
    {
        // $userId  = $request->user()->id;
        $perPage = $request->perPage ?? 10;
        $ulbId   = 11;
        $validator = Validator::make($request->all(), [
            'complainNo'     => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => False, 'msg' => $validator->messages()->first(), 'data' => ""]);
        }
        try {

            $response = array();
            $records = $this->mTcComplaint
                ->where('is_deactivate', 0)
                ->where('ulb_id', $ulbId);

            if (isset($request->tcId))
                $records = $records->where('user_id', $request->tcId);

            if (isset($request->complainNo))
                $records = $records->where('complain_no', $request->complainNo);

            if (isset($request->wardNo))
                $records = $records->where('ward_no', $request->wardNo);

            $records = $records->orderBy('id', 'DESC')
                ->paginate($perPage);

            foreach ($records as $record) {
                $getuserdata = $this->GetUserDetails($record->user_id);
                $val['id']            = $record->id;
                $val['ward_no']       = $record->ward_no;
                $val['consumer_name'] = $record->consumer_name;
                $val['latitude']      = $record->latitude;
                $val['longitude']     = $record->longitude;
                $val['address']       = $record->address;
                $val['complain']      = $record->complain;
                $val['complain_no']   = $record->complain_no;
                $val['tcName']        = $getuserdata->name ?? "Citizen";
                $val['date']          = Carbon::create($record->complain_date)->format('d-m-Y');
                $response[] = $val;
            }

            $data['data'] = $response;
            $data['current_page'] = $records->currentPage();
            $data['last_page']    = $records->lastPage();
            $data['total']        = $records->total();
            $data['per_page']     = $perPage;

            return response()->json(['status' => True, 'data' => $data, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    /**
     * | Get Citizen Complain Details
     */
    public function citizenComplainDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'    => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => False, 'msg' => $validator->messages()->first(), 'data' => ""]);
        }

        try {
            $docUrl  = "https://deoghar.smartulb.co.in/swm-deoghar";
            // $docUrl  = "http://172.18.1.131:6969";
            $record = $this->mTcComplaint
                ->where('is_deactivate', 0)
                ->where('id', $request->id)
                ->first();

            if (!$record)
                return response()->json(['status' => False, 'msg' => "No Data Found", 'data' => ""]);

            $getuserdata = $this->GetUserDetails($record->user_id);
            $val['id']            = $record->id;
            $val['ward_no']       = $record->ward_no;
            $val['consumer_no']   = $record->consumer_no;
            $val['consumer_name'] = $record->consumer_name;
            $val['latitude']      = $record->latitude;
            $val['longitude']     = $record->longitude;
            $val['address']       = $record->address;
            $val['tc_remarks']    = $record->tc_remarks;
            $val['tl_remarks']    = $record->tl_remarks;
            $val['complain']      = $record->complain;
            $val['complain_no']   = $record->complain_no;
            $val['status']        = $record->status;
            $val['tc_photo']      = $docUrl . "/uploads/" . $record->tc_photo;
            $val['tl_photo']      = isset($record->tl_photo) ? $docUrl . "/uploads/" . $record->tl_photo : "";
            $val['tcName']        = $getuserdata->name;
            $val['date']          = Carbon::create($record->complain_date)->format('d-m-Y');

            return response()->json(['status' => True, 'data' => $val, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    /**
     * | Send Otp
     */
    public function sendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'mobileNo' => "required|digits:10|regex:/[0-9]{10}/", #exists:active_citizens,mobile|
                'type' => "nullable|in:Register,Forgot",
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()->first(), 'data' => ""]);
            }
            $mOtpRequest = new OtpRequest();
            $generateOtp = $this->generateOtp();
            $mOtpRequest->saveOtp($request, $generateOtp);

            $whatsaapData = (Whatsapp_Send(
                $request->mobileNo,
                "send_sms",
                [
                    "content_type" => "text",
                    [
                        "Complain Register",
                        $generateOtp
                    ]
                ]
            ));

            return response()->json(['status' => True, 'data' => '', 'msg' => $whatsaapData['status']], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    /**
     * | Generate Random OTP 
     */
    public function generateOtp()
    {
        $otp = str_pad(Carbon::createFromDate()->milli . random_int(100, 999), 6, 0);
        // $otp = 123123;
        return $otp;
    }

    /**
     * | Verify Otp
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobileNo' => "required|digits:10|regex:/[0-9]{10}/", #exists:active_citizens,mobile|
            'otp'      => "required|Numeric",
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => False, 'msg' => $validator->messages()->first(), 'data' => ""]);
        }

        try {
            $mOtpMaster             = new OtpRequest();
            $checkOtp = $mOtpMaster::orderByDesc("id")
                ->where('mobile_no', request()->input('mobileNo'))
                ->where('otp', request()->input('otp'))
                ->first();

            if (!$checkOtp) {
                throw new Exception("OTP not match!");
            }
            if ($checkOtp->expires_at < Carbon::now()) {
                $this->transerLog($checkOtp);
                throw new Exception("OTP is expired");
            }
            $checkOtp->use_date_time = Carbon::now();
            $request->merge([
                "tokenableType"  => $checkOtp->gettable(),
                "tokenableId"  => $checkOtp->id,
                "userType"     => $checkOtp->user_type,
                "userId"     => $checkOtp->user_id,
            ]);

            DB::beginTransaction();
            $checkOtp->update();
            $this->transerLog($checkOtp);

            $sms = "OTP Validated!";
            $response = [];
            DB::commit();

           return $this->responseMsgs(true, $sms, $response);
        } catch (Exception $e) {
            DB::rollBack();
           return $this->responseMsgs(false, $e->getMessage(), "");
        }
    }

    private function transerLog(OtpRequest $checkOtp)
    {
        $OldOtps =  OtpRequest::where("expires_at", Carbon::now())
            ->whereNotNull("expires_at")
            ->where(DB::raw("CAST(created_at AS Date)"), Carbon::now()->format("Y-m-d"))
            ->get();
        foreach ($OldOtps as $val) {
            $otpLog = $val->replicate();
            $otpLog->setTable('log_otp_requests');
            $otpLog->id = $val->id;
            $otpLog->save();
            $checkOtp->delete();
        }
        if ($checkOtp) {
            $otpLog = $checkOtp->replicate();
            $otpLog->setTable('log_otp_requests');
            $otpLog->id = $checkOtp->id;
            $otpLog->save();
            $checkOtp->delete();
        }
    }

    /**
     * | 
     */
    public function saleTransaction(Request $req)
    {
        try {
            // $req = new Request([
            //     "saleTxnId"   => "637635161297869671",
            //     "saleAmount"  => "5000.00",
            //     "mobileNo"    => "9555274574",
            //     "email"       => "pks247@gmail.com",
            //     "customerName" => "Pramod",
            //     "description" => "payment request",
            //     "skuIds"      => "ASD212121 ",
            //     "field1"      => "pks@okhddfc",
            //     "field2"      => 'abcd',
            //     "field3"      => null,
            //     "field4"      => null,
            //     "field5"      => null
            // ]);
            $api = "https://testcallbh.bonushub.co.in:9443/api/ecr/v1/saletxn";

            #_Encryption
            $cryptoKey = "F7556B6D2A034B0FA2EB387DA36F8D84"; // Replace with your actual crypto key
            $cryptoKeyHex = bin2hex($cryptoKey);
            $payload = [
                "saleTxnId"   => "637635161297869671",
                "saleAmount"  => "5000.00",
                "mobileNo"    => "9555274574",
                "email"       => "pks247@gmail.com",
                "customerName" => "Pramod",
                "description" => "payment request",
                "skuIds"      => "ASD212121 ",
                "field1"      => "pks@okhddfc",
                "field2"      => 'abcd',
                "field3"      => null,
                "field4"      => null,
                "field5"      => null
            ]; // Replace with your actual payload

            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

            // Encrypt the payload
            $encryptedPayload = Crypt::encryptString($payloadJson);

            $transfer = [
                "payLoadData"   => $encryptedPayload,
            ];

            // $encryptedPayloadHex = bin2hex($encryptedPayload);
            // $encryptedPayloadHexEncoded = urlencode($encryptedPayloadHex);
            // $url = "https://testcallbh.bonushub.co.in:9443/api/ecr/v1/saletxn" . $encryptedPayloadHexEncoded;

            return   $returnData = Http::withHeaders([
                "Client_apikey"         => "MDVGMDY0Q0MyMkRDNDE2MDlEMzhGRTNGQ0FBMTYyRTA=",
            ])->post("$api", $transfer);
            
        } catch (Exception $e) {
            return $this->responseMsgs(false,  $e->getMessage(), "");
        }
    }

    # generate order id and save in table
    
    
}
