<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\Ward;
use App\Traits\Api\Helpers;
use Exception;
use Illuminate\Http\Request;

use function App\Traits\Api\responseMsgs;

class CitizenController extends Controller
{
    use Helpers;

    protected $dbConn;
    protected $mConsumer;
    protected $mWard;

    public function __construct(Request $request)
    {
        $this->dbConn = "db_swm";

        $this->mWard     = new Ward($this->dbConn);
        $this->mConsumer = new Consumer($this->dbConn);
        // $this->Demand = new Demand($this->dbConn);
        // $this->Apartment = new Apartment($this->dbConn);
        // $this->ConsumerType = new ConsumerType($this->dbConn);
        // $this->ConsumerCategory = new ConsumerCategory($this->dbConn);
        // $this->ConsumerDeactivateDeatils = new ConsumerDeactivateDeatils($this->dbConn);
        // $this->Transaction = new Transaction($this->dbConn);
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
            $data    = $this->mConsumer->where('consumer_category_id', 1)->where('is_deactivate', 0);

            if (request()->has('consumerNo'))
                $data->where('consumer_no', request()->input('consumerNo'));

            if (request()->has('consumerName'))
                $data->where('name', 'like', '%' . request()->input('consumerName') . '%');

            if (request()->has('mobileNo'))
                $data->where('mobile_no', request()->input('mobileNo'));

            $data = $data->paginate($perPage);
            return $this->responseMsgs(true, "Residential Consumer", $data);
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
            $data    = $this->mConsumer->where('consumer_category_id', '<>', 1)->where('is_deactivate', 0);

            if (request()->has('consumerNo'))
                $data->where('consumer_no', request()->input('consumerNo'));

            if (request()->has('consumerName'))
                $data->where('name', 'like', '%' . request()->input('consumerName') . '%');

            if (request()->has('mobileNo'))
                $data->where('mobile_no', request()->input('mobileNo'));

            $data = $data->paginate($perPage);
            return $this->responseMsgs(true, "Commercial Consumer", $data);
        } catch (Exception $e) {
            return $this->responseMsgs(true,  $e->getMessage(), "");
        }
    }
}
