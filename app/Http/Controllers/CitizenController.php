<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Collections;
use App\Models\Consumer;
use App\Models\Demand;
use App\Models\Transaction;
use App\Models\Ward;
use App\Repository\iMasterRepository;
use App\Repository\MasterRepository;
use App\Traits\Api\Helpers;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

    public function __construct(Request $request)
    {
        $this->dbConn = "db_swm";

        $this->mWard     = new Ward($this->dbConn);
        $this->mConsumer = new Consumer($this->dbConn);
        $this->mDemand   = new Demand($this->dbConn);
        $this->mTransaction = new Transaction($this->dbConn);
        $this->mCollections = new Collections($this->dbConn);
        $this->mApartment   = new Apartment($this->dbConn);
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
            $ulbId    = "21";
            $wardList = $this->mWard;
            $wardList = $wardList->where('ulb_id', $ulbId)->orderBy('sqorder')->get();

            return $this->responseMsgs(true, "Ward List", $wardList);
        } catch (Exception $e) {
            return $this->responseMsgs(true,  $e->getMessage(), "");
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
            return $this->responseMsgs(true,  $e->getMessage(), "");
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
            return $this->responseMsgs(true,  $e->getMessage(), "");
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

                $val['transaction_no']    = $trans->transaction_no;
                $val['payment_mode']      = $trans->payment_mode;
                $val['transaction_date']  = Carbon::create($trans->transaction_date)->format('d-m-Y');
                $val['total_payable_amt'] = $trans->total_payable_amt;
                $val['demand_from']       = ($firstrecord) ? Carbon::create($firstrecord->payment_from)->format('d-m-Y') : '';
                $val['demand_upto']       = ($lastrecord) ? Carbon::create($lastrecord->payment_to)->format('d-m-Y') : '';
                $val['tc_name']           = $getuserdata->name;
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
            return $this->responseMsgs(true,  $e->getMessage(), "");
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
            if (isset($request->consumerId))
                $demand = $demand->where('consumer_id', $request->consumerId);
            if (isset($request->apartmentId))
                $demand = $demand->join('swm_consumers as a', 'swm_demands.consumer_id', '=', 'a.id')
                    ->where('a.apartment_id', $request->apartmentId);
            $demand = $demand->where('paid_status', 0)
                ->where('swm_demands.is_deactivate', 0)
                // ->where('swm_demands.ulb_id', $ulbId)
                ->groupBy('payment_to')
                ->orderBy('payment_to', 'asc')
                ->get();

            return $this->responseMsgs(true, "Payment Upto Data", $demand);
        } catch (Exception $e) {
            return $this->responseMsgs(true,  $e->getMessage(), "");
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
            return $this->responseMsgs(true,  $e->getMessage(), "");
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
            $ulbId = 21;
            $perPage = $request->perPage ?? 10;
            $apartmentDtls = $this->mApartment->where('swm_apartments.id', $request->id)
                ->where('swm_apartments.is_deactivate', 0)
                ->paginate($perPage);

            return $this->responseMsgs(true, "Apartment Details", $apartmentDtls);
        } catch (Exception $e) {
            return $this->responseMsgs(true,  $e->getMessage(), "");
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
            $ulbId = 21;

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

            $data['id'] = $apartmentDtls->id;
            $data['ward_no'] = $apartmentDtls->ward_no;
            $data['apartment_name'] = $apartmentDtls->apt_name;
            $data['apartment_code'] = $apartmentDtls->apt_code;
            $data['address'] = $apartmentDtls->apt_address;
            $data['consumerDtls'] = $consumerArr;

            return $this->responseMsgs(true, "Apartment Details By Id", $data);
        } catch (Exception $e) {
            return $this->responseMsgs(true,  $e->getMessage(), "");
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
                "payUpto"    => "required|date",
                "consumerId" => "required",
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
            if ((isset($request->consumerId) || isset($request->apartmentId)) && isset($request->payUpto)) {

                $demand = $this->mDemand;

                if (isset($request->apartmentId)) {
                    $demand = $demand->join('swm_consumers as c', 'swm_demands.consumer_id', '=', 'c.id')
                        ->where('c.apartment_id', $request->apartmentId)
                        ->where('c.is_deactivate', 0);
                } else {
                    $demand = $demand->where('consumer_id', $request->consumerId);
                }
                $demand = $demand->where('paid_status', 0)
                    // ->where('swm_demands.ulb_id', $ulbId)
                    ->where('swm_demands.is_deactivate', 0)
                    ->whereDate('swm_demands.payment_to', '<=', $request->payUpto)
                    ->orderBy('swm_demands.id', 'asc')
                    ->sum('total_tax');

                $totalDmd = $demand;
                $paymentUptoDate = date('Y-m-t', strtotime($request->payUpto));

                $response['totaldemand'] = $totalDmd;
                $response['paymentUptoDate'] = $paymentUptoDate;
            }

            return $this->responseMsgs(true, "Total Demand", $response);
        } catch (Exception $e) {
            return $this->responseMsgs(true,  $e->getMessage(), "");
        }
    }
}
