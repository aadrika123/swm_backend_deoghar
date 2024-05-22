<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\Demand;
use App\Models\Transaction;
use App\Models\Ward;
use App\Repository\iMasterRepository;
use App\Repository\MasterRepository;
use App\Traits\Api\Helpers;
use Exception;
use Illuminate\Http\Request;
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

    public function __construct(Request $request)
    {
        $this->dbConn = "db_swm";

        $this->mWard     = new Ward($this->dbConn);
        $this->mConsumer = new Consumer($this->dbConn);
        $this->mDemand   = new Demand($this->dbConn);
        $this->mTransaction = new Transaction($this->dbConn);
        // $this->Apartment = new Apartment($this->dbConn);
        // $this->ConsumerType = new ConsumerType($this->dbConn);
        // $this->ConsumerCategory = new ConsumerCategory($this->dbConn);
        // $this->ConsumerDeactivateDeatils = new ConsumerDeactivateDeatils($this->dbConn);
        // $this->TransactionDetails = new TransactionDetails($this->dbConn);
        // $this->TransactionDeactivate = new TransactionDeactivate($this->dbConn);
        // $this->GeoLocation = new GeoLocation($this->dbConn);
        // $this->CosumerReminder = new CosumerReminder($this->dbConn);
        // $this->Collections = new Collections($this->dbConn);
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
            $consumer = $this->mConsumer
                ->select('swm_consumers.id', 'ward_no', 'swm_consumers.name', 'consumer_no', 'mobile_no', 'address', 'swm_consumer_types.name as consumer_type', 'swm_consumer_categories.name as consumer_category')
                ->join('swm_consumer_types', 'swm_consumer_types.id', 'swm_consumers.consumer_type_id')
                ->join('swm_consumer_categories',  'swm_consumer_categories.id', 'swm_consumers.consumer_category_id')
                ->where('consumer_category_id', 1)
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

            if (isset($consumer->id))
                $tranDtls = $this->mTransaction->select('id', 'transaction_no', 'transaction_date', 'payment_mode', 'total_payable_amt')
                    ->where('swm_transactions.consumer_id', $consumer->id);

            if (isset($request->apartmentId))
                $tranDtls = $this->mTransaction->select('id', 'transaction_no', 'transaction_date', 'payment_mode', 'total_payable_amt')
                                               ->where('swm_transactions.apartment_id', $request->apartmentId);

            $tranDtls = $tranDtls->orderBy('swm_transactions.id', 'desc')->get();

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
            $con['transaction_details'] = $tranDtls;
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
    public function apartmentList(Request $request, iMasterRepository $iMasterRepo)
    {
        //  MasterRepository(iMasterRepository ,$iMasterRepo);
        // MasterRepository::getApartmentList();
        // MasterRepository::getApartmentList();
        // iMasterRepository $master->getApartmentList($request);
    }
}
