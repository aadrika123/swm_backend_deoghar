<?php

namespace App\Repository;

use App\Models\Consumer;
use App\Models\Demand;
use App\Models\Apartment;
use App\Models\ConsumerType;
use App\Models\ConsumerCategory;
use App\Models\ConsumerDeactivateDeatils;
use App\Models\ConsumerEditLog;
use App\Models\Transaction;
use App\Models\TransactionDetails;
use App\Models\TransactionDeactivate;
use App\Models\GeoLocation;
use App\Models\CosumerReminder;
use App\Models\Collections;
use App\Models\TransactionVerification;
use App\Models\TransactionModeChange;
use App\Models\BankCancel;
use App\Models\BankCancelDetails;
use App\Models\PaymentDeny;
use App\Models\DemandLog;
use App\Models\DemandAdjustment;
use App\Models\RazorpayReq;
use App\Models\RazorpayResponse;
use App\Models\TcComplaint;
use App\Models\Routes;
use App\Models\TblUserMstr;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Traits\Api\Helpers;
use PhpOption\None;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Razorpay\Api\Card;
use App\BLL\SwmDemand;

/**
 * | Created On-08-09-2022 
 * | Created By-
 * | Created For- Consumer related api 
 */
class ConsumerRepository implements iConsumerRepository
{

    use Helpers;

    protected $dbConn;
    protected $Consumer;
    protected $Demand;
    protected $Apartment;
    protected $ConsumerType;
    protected $ConsumerCategory;
    protected $ConsumerDeactivateDeatils;
    protected $Transaction;
    protected $TransactionDetails;
    protected $TransactionDeactivate;
    protected $GeoLocation;
    protected $CosumerReminder;
    protected $Collections;
    protected $TransactionVerification;
    protected $BankCancel;
    protected $BankCancelDetails;
    protected $PaymentDeny;
    protected $TransactionModeChange;
    protected $ConsumerEditLog;
    protected $DemandLog;
    protected $DemandAdjustment;
    protected $TcComplaint;
    protected $Routes;

    public function __construct(Request $request)
    {
        $this->dbConn = $this->GetSchema($request->bearerToken());

        $this->Consumer = new Consumer($this->dbConn);
        $this->Demand = new Demand($this->dbConn);
        $this->Apartment = new Apartment($this->dbConn);
        $this->ConsumerType = new ConsumerType($this->dbConn);
        $this->ConsumerCategory = new ConsumerCategory($this->dbConn);
        $this->ConsumerDeactivateDeatils = new ConsumerDeactivateDeatils($this->dbConn);
        $this->Transaction = new Transaction($this->dbConn);
        $this->TransactionDetails = new TransactionDetails($this->dbConn);
        $this->TransactionDeactivate = new TransactionDeactivate($this->dbConn);
        $this->GeoLocation = new GeoLocation($this->dbConn);
        $this->CosumerReminder = new CosumerReminder($this->dbConn);
        $this->Collections = new Collections($this->dbConn);
        $this->TransactionVerification = new TransactionVerification($this->dbConn);
        $this->BankCancel = new BankCancel($this->dbConn);
        $this->BankCancelDetails = new BankCancelDetails($this->dbConn);
        $this->PaymentDeny = new PaymentDeny($this->dbConn);
        $this->TransactionModeChange = new TransactionModeChange($this->dbConn);
        $this->ConsumerEditLog = new ConsumerEditLog($this->dbConn);
        $this->DemandLog = new DemandLog($this->dbConn);
        $this->DemandAdjustment = new DemandAdjustment($this->dbConn);
        $this->TcComplaint = new TcComplaint($this->dbConn);
        $this->Routes = new Routes($this->dbConn);
    }

    public function ConsumerList(Request $request)
    {
        $ulbId = $this->GetUlbId($request->user()->id);
        try {
            $conArr = array();
            if (isset($request->id) || isset($request->consumerNo) || isset($request->consumerName) || isset($request->mobileNo)) {
                if (isset($request->id)) {
                    $field = 'swm_consumers.id';
                    $operator = '=';
                    $value = $request->id;
                }

                if (isset($request->consumerNo)) {
                    $field = 'consumer_no';
                    $operator = '=';
                    $value = $request->consumerNo;
                }

                if (isset($request->consumerName)) {
                    $field = 'swm_consumers.name';
                    $operator = 'like';
                    $value = '%' . $request->consumerName . '%';
                }

                if (isset($request->mobileNo)) {
                    $field = 'mobile_no';
                    $operator = '=';
                    $value = $request->mobileNo;
                }

                $consumerList = $this->Consumer->join('swm_consumer_categories', 'swm_consumers.consumer_category_id', '=', 'swm_consumer_categories.id')
                    ->join('swm_consumer_types', 'swm_consumers.consumer_type_id', '=', 'swm_consumer_types.id')
                    ->select(DB::raw('swm_consumers.*, swm_consumer_categories.name as category, swm_consumer_types.name as type'))
                    ->where('swm_consumers.ulb_id', $ulbId);
                if (isset($request->wardNo))
                    $consumerList = $consumerList->where('ward_no', $request->wardNo);
                $consumerList = $consumerList->where($field, $operator, $value)
                    ->orderBy('swm_consumers.id', 'DESC')
                    ->paginate(
                        $perPage = 1000,
                        $columns = ['*'],
                        $pageName = 'consumers'
                    );

                //echo "<pre/>";print_r($consumerList);
                foreach ($consumerList as $consumer) {
                    $demand = $this->Demand->where('consumer_id', $consumer->id)
                        ->where('paid_status', 0)
                        //->whereBetween('is_deactivate', [0,1])
                        ->where('ulb_id', $ulbId)
                        ->orderBy('id', 'asc')
                        ->get();
                    $total_tax = 0.00;
                    $demand_upto = '';
                    $paid_status = 'Paid';
                    $monthlyDemand = 0;
                    $demand_form = '';
                    $i = 0;

                    $trans = $this->Transaction->where('consumer_id', $consumer->id)->count('*');

                    foreach ($demand as $dmd) {
                        if ($i == 0)
                            $demand_form = date('d-m-Y', strtotime($dmd->payment_from));
                        $i++;
                        $demand_upto = date('d-m-Y', strtotime($dmd->payment_to));
                        $monthlyDemand = $dmd->total_tax;
                        $total_tax += $dmd->total_tax;
                        $paid_status = 'Unpaid';
                    }

                    $geoLocation = $this->GeoLocation
                        ->where('consumer_id', $consumer->id)
                        ->first();

                    //

                    $con['id'] = $consumer->id;
                    $con['wardNo'] = $consumer->ward_no;
                    $con['consumerName'] = $consumer->name;
                    $con['apartmentId'] = $consumer->apartment_id;
                    $con['consumerNo'] = $consumer->consumer_no;
                    $con['holdingNo'] = $consumer->holding_no;
                    $con['Address'] = $consumer->address;
                    $con['pinCode'] = $consumer->pincode;
                    $con['cansumerCategory'] = $consumer->category;
                    $con['cansumerType'] = $consumer->type;
                    $con['mobileNo'] = $consumer->mobile_no;
                    $con['activeDemandDetails'] = $demand;
                    $con['monthlyDemand'] = $monthlyDemand;
                    $con['totalDemand'] = $total_tax;
                    $con['demandFrom'] = $demand_form;
                    $con['demandUpto'] = $demand_upto;
                    $con['paidStatus'] = $paid_status;
                    $con['applyBy'] = ($consumer->user_id) ? $this->GetUserDetails($consumer->user_id)->name : '';
                    $con['applyDate'] = date("d-m-Y", strtotime($consumer->entry_date));
                    $con['status'] = ($consumer->is_deactivate == 0) ? 'Active' : 'Deactive';
                    $con['editApplicable'] = ($trans == 0) ? true : false;
                    $con['isgeotagged'] = ($geoLocation) ? true : false;
                    $conArr[] = $con;
                }
                return response()->json(['status' => True, 'data' => $conArr, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $conArr, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function ApartmentList(Request $request)
    {
        $ulbId = $this->GetUlbId($request->user()->id);
        try {

            $conArr = array();

            if (isset($request->apartmentId) || isset($request->apartmentName)) {
                if (isset($request->apartmentId)) {
                    $field = 'id';
                    $operator = '=';
                    $value = $request->apartmentId;
                }

                if (isset($request->apartmentName)) {
                    $field = 'apt_name';
                    $operator = 'ilike';
                    $value = '%' . $request->apartmentName . '%';
                }

                $apartmentList = $this->Apartment->where($field, $operator, $value)
                    ->where('ulb_id', $ulbId)
                    ->orderBy('apt_name', 'ASC')
                    ->paginate(100);

                foreach ($apartmentList as $apartment) {

                    $demand = $this->GetDemand($this->dbConn, $apartment->id, 'Apartment', $ulbId);
                    $con['id'] = $apartment->id;
                    $con['wardNo'] = $apartment->ward_no;
                    $con['apartmentName'] = $apartment->apt_name;
                    $con['apartmentCode'] = $apartment->apt_code;
                    $con['address'] = $apartment->apt_address;
                    $con['pinCode'] = $apartment->pincode;
                    $con['mobileNo'] = $apartment->mobile_no;
                    $con['totalDemand'] = ($demand) ? $demand['demandAmt'] : '0.00';
                    $con['demandFrom'] = ($demand) ? $demand['demandFrom'] : '';
                    $con['demandUpto'] = ($demand) ? $demand['demandUpto'] : '';
                    $con['paidStatus'] = ($demand) ? 'Unpaid' : 'Paid';
                    $con['status'] = ($apartment->is_deactivate == 0) ? 'Active' : 'Deactive';


                    $conArr[] = $con;
                }
                return response()->json(['status' => True, 'data' => $conArr, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $conArr, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function getApartmentDetails(Request $request)
    {

        $ulbId = $this->GetUlbId($request->user()->id);
        try {

            $conArr = array();
            if (isset($request->id)) {

                $sql = "SELECT a.*, c.id as consumer_id,  c.pincode as pincode, c.name as consumer_name, c.mobile_no as contactno, c.holding_no, c.consumer_no,c.user_id,c.entry_date FROM swm_apartments a 
                LEFT JOIN (SELECT * FROM swm_consumers WHERE is_deactivate=0) c on c.apartment_id=a.id
                WHERE a.id=" . $request->id . " and a.ulb_id=" . $ulbId .
                    "order by entry_date desc";
                $apartments = DB::connection($this->dbConn)->select($sql);

                $apt_tot_tax = 0;
                $aptmonthlyDemand = 0;

                foreach ($apartments as $apartment) {

                    $demand = $this->Demand->where('consumer_id', $apartment->consumer_id)
                        ->where('paid_status', 0)
                        //->where('is_deactivate', 0)
                        ->where('ulb_id', $ulbId)
                        ->get();

                    $total_tax = 0.00;
                    $demand_upto = '';
                    $paid_status = 'Paid';
                    $monthlyDemand = 0;
                    $demand_form = '';
                    $i = 0;
                    $trans = $this->Transaction->where('apartment_id', $apartment->id)->count('*');
                    foreach ($demand as $dmd) {
                        if ($i == 0)
                            $demand_form = date('d-m-Y', strtotime($dmd->payment_from));
                        $i++;
                        $total_tax += $dmd->total_tax;
                        $demand_upto = date('d-m-Y', strtotime($dmd->payment_to));
                        $paid_status = 'Unpaid';
                        $monthlyDemand = $dmd->total_tax;
                    }



                    $apt_tot_tax += $total_tax;
                    $aptmonthlyDemand += $monthlyDemand;
                    $con['id'] = $apartment->id;
                    $con['wardNo'] = $apartment->ward_no;
                    $con['apartmentName'] = $apartment->apt_name;
                    $con['apartmentCode'] = $apartment->apt_code;
                    $con['consumerId'] = $apartment->consumer_id;
                    $con['consumerName'] = $apartment->consumer_name;
                    $con['consumerNo'] = $apartment->consumer_no;
                    $con['consumerMobileNo'] = $apartment->contactno;
                    $con['holdingNo'] = $apartment->holding_no;
                    $con['address'] = $apartment->apt_address;
                    $con['mobileNo'] = $apartment->contactno;
                    $con['pinCode'] = $apartment->pincode;
                    $con['activeDemandDetails'] = $demand;
                    $con['monthlyDemand'] = $monthlyDemand;
                    $con['totaldemand'] = $total_tax;
                    $con['demandFrom'] = $demand_form;
                    $con['demandUpto'] = $demand_upto;
                    $con['paidStatus'] = $paid_status;
                    $con['applyBy'] = ($apartment->user_id) ? $this->GetUserDetails($apartment->user_id)->name : '';
                    $con['applyDate'] = ($apartment->entry_date) ? date("d-m-Y", strtotime($apartment->entry_date)) : '';
                    $con['editApplicable'] = ($trans == 0) ? true : false;


                    $conArr[] = $con;
                }

                $geoLocation = $this->GeoLocation
                    ->where('apartment_id', $apartment->id)
                    ->first();
                $isgeotagged = ($geoLocation) ? true : false;

                return response()->json(['status' => True, 'data' => $conArr, 'totalAptDemand' => $apt_tot_tax, 'totalAptMonthlyDemand' => $aptmonthlyDemand, 'isgeotagged' => $isgeotagged, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $conArr, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function ConsumerAdd(Request $request)
    {

        try {

            $validator = Validator::make($request->all(), [
                'consumerName' => 'required',
                'wardNo' => 'required',
                'mobileNo' => 'required',
                'address' => 'required',
                'consumerCategory' => 'required',
                'consumerType' => 'required',
                'demandFrom' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            $apartId = Null;
            $apartCode = '';
            $apartCount = 0;
            $consumerNo = '';
            $apartName = '';
            $userId = $request->user()->id;
            $ulbId = $this->GetUlbId($userId);


            $consumer = $this->Consumer;
            $consumer->ward_no = $request->wardNo;
            $consumer->holding_no = $request->holdingNo;
            $consumer->name = $request->consumerName;
            $consumer->mobile_no = $request->mobileNo;
            $consumer->address = $request->address;
            $consumer->firm_name = $request->firmName;
            $consumer->pincode = $request->pinCode;
            $consumer->consumer_category_id = $request->consumerCategory;
            $consumer->consumer_type_id = $request->consumerType;
            $consumer->license_no = $request->licenseNo;
            $consumer->user_id = $userId;
            $consumer->entry_date = date('Y-m-d');
            $consumer->stampdate = date('Y-m-d H:i:s');
            $consumer->is_deactivate = 0;
            $consumer->ulb_id = $ulbId;
            $consumer->save();


            $consumerUpdate = $this->Consumer->find($consumer->id);

            if (isset($request->apartmentId)) {

                $apart = $this->Apartment->select('apt_code', 'apt_name')->where('id', $request->apartmentId)->where('ulb_id', $ulbId)->first();

                $getConsum = $this->Consumer->select('consumer_no')->where('apartment_id', $request->apartmentId)->where('ulb_id', $ulbId);
                $oldConsumerNo = $getConsum->first();
                $apartId = $request->apartmentId;
                $apartCode = $apart->apt_code;
                $apartName = $apart->apt_name;
                if ($getConsum->count() > 0) {
                    $apartCount = $getConsum->count() + 1;
                    $consumerNo = substr($oldConsumerNo->consumer_no, 0, 10) . str_pad($apartCount, 5, "0", STR_PAD_LEFT);
                } else {
                    $serialNo = '0001';
                    $wardCreated = str_pad($request->wardNo, 2, "0", STR_PAD_LEFT);
                    $consumerTypeCreated = str_pad($request->consumerType, 2, "0", STR_PAD_LEFT);
                    $randCreated = str_pad($consumer->id, 5, "0", STR_PAD_LEFT);

                    $consumerNo = $wardCreated . $request->consumerCategory . $consumerTypeCreated . $randCreated . $serialNo;
                }
            }

            if ((!isset($request->apartmentId) || empty($request->apartmentId)) || $apartCount == 1) {
                $serialNo = '0001';
                $wardCreated = str_pad($request->wardNo, 2, "0", STR_PAD_LEFT);
                $consumerTypeCreated = str_pad($request->consumerType, 2, "0", STR_PAD_LEFT);
                $randCreated = str_pad($consumer->id, 5, "0", STR_PAD_LEFT);

                $consumerNo = $wardCreated . $request->consumerCategory . $consumerTypeCreated . $randCreated . $serialNo;
            }
            $consumerUpdate->apartment_id = $apartId;
            $consumerUpdate->consumer_no = $consumerNo;
            $consumerUpdate->save();


            $consumerType = $this->ConsumerType->select('rate', 'name')
                ->where('id', $request->consumerType)
                ->first();
            //Generate Demand
            $demand = $this->GenerateDemand($this->dbConn, $consumer->id, $consumerType->rate, $request->demandFrom, $userId, $ulbId);
            //


            $response = array();
            $response['consumerId'] = $consumer->id;
            $response['wardNo'] = $request->wardNo;
            $response['apartmentId'] = $request->apartmentId;
            $response['holdingNo'] = $request->holdingNo;
            $response['consumerNo'] = $consumerNo;
            $response['consumerName'] = $request->consumerName;
            $response['apartmentName'] = $apartName;
            $response['mobileNo'] = $request->mobileNo;
            $response['address'] = $request->address;
            $response['pinCode'] = $request->pinCode;
            $response['consumerCategory'] = $this->ConsumerCategory->select('name')->first()->name;
            $response['consumerType'] = $consumerType->name;
            $response['demandFrom'] = $request->demandFrom;
            $response['demandDetails'] = $demand;
            $response['appliedBy'] = $userId;
            $response['appliedDate'] = date('Y-m-d');

            #_Whatsaap Message
            if (strlen($consumer->mobile_no) == 10) {
                $whatsapp2 = (Whatsapp_Send(
                    $consumer->mobile_no,
                    "all_module_succesfull_generation",
                    [
                        "content_type" => "text",
                        [
                            $request->consumerName ?? "Citizen",
                            "SWM",
                            "Consumer No.",
                            $consumerNo,
                            "1800123123"
                        ]
                    ]
                ));
            }

            return response()->json(['status' => true, 'data' => $response, 'msg' => "Consumer created and demand generated successfully"], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function RenterFormData(Request $request)
    {

        try {
            $ulbId = $this->GetUlbId($request->user()->id);
            $response = array();

            if (isset($request->consumerId)) {

                $getconsumer = $this->Consumer->where('id', $request->consumerId)
                    ->where('ulb_id', $ulbId)
                    ->first();

                $response['wardNo'] = $getconsumer->ward_no;
                $response['ownerName'] = $getconsumer->name;
                $response['holdingNo'] = $getconsumer->holding_no;
                $response['consumerCategoryList'] = $this->ConsumerCategory->get();


                return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function EditConsumerDetailsById(Request $request)
    {

        try {
            $ulbId = $this->GetUlbId($request->user()->id);
            $response = array();
            if (isset($request->id)) {

                $response = (array)$this->ConsumerList($request)->original['data'][0];

                if (count($response) > 0 && !empty($response['apartmentId'])) {
                    $apart = $this->Apartment->select('apt_code', 'apt_name')
                        ->where('id', $response['apartmentId'])
                        ->where('ulb_id', $ulbId)
                        ->first();

                    $response['apartmentName'] = $apart->apt_name;
                    $response['apartmentCode'] = $apart->apt_code;
                }
                return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }



    public function DeactivateConsumer(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            $response = array();
            if (isset($request->consumerId)) {

                $consDtls = $this->ConsumerDeactivateDeatils;
                $consDtls->consumer_id = $request->consumerId;
                $consDtls->remarks = ($request->remarks) ? $request->remarks : "";
                $consDtls->deactivated_by = $userId;
                $consDtls->deactivation_date = date('Y-m-d');
                $consDtls->ip_address = $request->ip();
                $consDtls->stampdate = date('Y-m-d H:i:s');
                $consDtls->ulb_id = $ulbId;
                $consDtls->save();

                if ($consDtls->id > 0) {
                    $consumer = $this->Consumer;
                    $consumer = $consumer->find($request->consumerId);
                    $consumer->is_deactivate = 1;
                    $consumer->save();

                    $this->Demand->where('consumer_id', $request->consumerId)
                        ->where('paid_status', 0)
                        ->where('ulb_id', $ulbId)
                        ->update(['is_deactivate' => 1]);

                    return response()->json(['status' => True, 'data' => '', 'msg' => 'Deactivated Successfully'], 200);
                } else {
                    return response()->json(['status' => False, 'data' => $response, 'msg' => 'Deactivate issue, please check'], 200);
                }
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function PaymentData(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            $response = array();
            if (isset($request->consumerId) || isset($request->apartmentId)) {

                $demand = $this->Demand->select('payment_to');
                if (isset($request->consumerId))
                    $demand = $demand->where('consumer_id', $request->consumerId);
                if (isset($request->apartmentId))
                    $demand = $demand->join('swm_consumers as a', 'swm_demands.consumer_id', '=', 'a.id')
                        ->where('a.apartment_id', $request->apartmentId);
                $demand = $demand->where('paid_status', 0)
                    //->whereBetween('swm_demands.is_deactivate',[0,1])
                    ->where('swm_demands.ulb_id', $ulbId)
                    ->groupBy('payment_to')
                    ->orderBy('payment_to', 'asc')
                    ->get();

                // $totalDmd = 0;
                // $paymentUptoMonth = '';
                // foreach ($demand as $dmd) {
                //     $totalDmd += $dmd->total_tax;
                //     $paymentUptoMonth = date('M', strtotime($dmd->payment_to));
                // }
                // $response['demand'] = $demand;
                // $response['totaldemand'] = $totalDmd;
                // $response['paymentUptoMonth'] = $paymentUptoMonth;
                $response['demand'] = $demand;

                return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function CalculatedAmount(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);

        try {
            $response = array();
            if ((isset($request->consumerId) || isset($request->apartmentId)) && isset($request->payUpto)) {

                $demand = $this->Demand;

                if (isset($request->apartmentId)) {
                    $demand = $demand->join('swm_consumers as c', 'swm_demands.consumer_id', '=', 'c.id')
                        ->where('c.apartment_id', $request->apartmentId)
                        ->where('c.is_deactivate', 0);
                } else {
                    $demand = $demand->where('consumer_id', $request->consumerId);
                }
                $demand = $demand->where('paid_status', 0)
                    ->where('swm_demands.ulb_id', $ulbId)
                    //->where('swm_demands.is_deactivate', 0)
                    ->whereDate('swm_demands.payment_to', '<=', $request->payUpto)
                    ->orderBy('swm_demands.id', 'asc')
                    ->sum('total_tax');

                $totalDmd = $demand;
                $paymentUptoDate = date('Y-m-t', strtotime($request->payUpto));

                $response['totaldemand'] = $totalDmd;
                $response['paymentUptoDate'] = $paymentUptoDate;


                return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function DashboardData(Request $request)
    {

        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            $response = array();

            if (isset($request->fromDate) && isset($request->toDate)) {

                $From = Carbon::create($request->fromDate)->format('Y-m-d');
                $Upto = Carbon::create($request->toDate)->format('Y-m-d');

                $whereParam = "";
                if (isset($request->wardNo))
                    $whereParam = " and ward_no=" . $request->wardNo;

                $sql = "SELECT count(*) as total_consumer,
                count(CASE WHEN consumer_category_id = 1 THEN id end) as residential,
                count(CASE WHEN consumer_category_id != 1 THEN id end) as commercial
                FROM swm_consumers where (entry_date between '" . $From . "' and '" . $Upto . "') and ulb_id=" . $ulbId . " " . $whereParam;

                $Consumer = DB::connection($this->dbConn)->select($sql);

                $totalDmd = $this->Demand
                    ->where('paid_status', 0)
                    ->where('is_deactivate', 0)
                    ->where('ulb_id', $ulbId)
                    ->whereBetween('demand_date', [$From, $Upto])
                    ->sum('total_tax');

                $Collection = $this->Transaction->select('paid_status', 'transaction_date', 'total_payable_amt')
                    ->leftjoin('swm_transaction_deactivates', 'swm_transaction_deactivates.transaction_id', '=', 'swm_transactions.id')
                    ->where('ulb_id', $ulbId)
                    ->whereNull('transaction_id')
                    ->whereBetween('transaction_date', [$From, $Upto])->get();

                $nUpto = Carbon::create($Upto)->addHour('24');
                $totalAdjustment = $this->DemandAdjustment->where('is_deactivate', 0)
                    ->where('ulb_id', $ulbId)
                    ->whereBetween('stampdate', [$From, $nUpto])
                    ->sum('adjust_amount');

                $TotalConsumer = 0;
                $totalResidential = 0;
                $totalCommercial = 0;
                if ($Consumer) {
                    $Consumer = $Consumer[0];
                    $TotalConsumer = $Consumer->total_consumer;
                    $totalResidential = $Consumer->residential;
                    $totalCommercial = $Consumer->commercial;
                }

                $totalCollection = 0;
                $pendingCollection = 0;

                foreach ($Collection as $coll) {
                    if ($coll->paid_status == 1 && $coll->paid_status != 0) {
                        $totalCollection += $coll->total_payable_amt;
                    } else if ($coll->paid_status == 2) {
                        $pendingCollection += $coll->total_payable_amt; // for cheque or dd
                    }
                }


                $response['totalDemand'] = $totalDmd;
                $response['totalConsumer'] = $TotalConsumer;
                $response['totalCollection'] = $totalCollection;
                $response['pendingCollection'] = $pendingCollection;
                $response['totalAdjustment'] = $totalAdjustment;
                $response['totalResidenstialConsumer'] = $totalResidential;
                $response['totalCommercialConsumer'] = $totalCommercial;
            }

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function GetTrancation(Request $request)
    {

        $ulbId = $this->GetUlbId($request->user()->id);
        try {

            $response = array();
            if (isset($request->transactionNo)) {
                // $sql = "SELECT t.*,c.name as consumer_name,consumer_no,a.apt_code,a.apt_name,u.name as transaction_by,u.contactno,
                //                 c.ward_no, a.ward_no as apt_ward, c.holding_no, c.address, a.apt_address,c.apartment_id,a.id as apt_id,demand_from,demand_upto
                //         FROM swm_transactions t
                //             LEFT JOIN swm_consumers c on t.consumer_id=c.id
                //             LEFT JOIN swm_apartments a on t.apartment_id=a.id
                //             LEFT JOIN swm_transaction_deactivates td on td.transaction_id=t.id
                //             LEFT JOIN (select min(payment_from) as demand_from, max(payment_to) as demand_upto,transaction_id FROM swm_collections group by transaction_id) cl on cl.transaction_id=t.id
                //             JOIN db_master.view_user_mstr u on t.user_id=u.id
                //                 WHERE t.transaction_no ='" . $request->transactionNo . "' and t.ulb_id=" . $ulbId . " and td.id is null";

                # New Query
                $sql = "SELECT t.*,c.name as consumer_name,consumer_no,a.apt_code,a.apt_name,
                                c.ward_no, a.ward_no as apt_ward, c.holding_no, c.address, a.apt_address,c.apartment_id,a.id as apt_id,demand_from,demand_upto
                        FROM swm_transactions t
                            left JOIN swm_consumers c on t.consumer_id=c.id
                            LEFT JOIN swm_apartments a on t.apartment_id=a.id
                            LEFT JOIN swm_transaction_deactivates td on td.transaction_id=t.id
                            LEFT JOIN (select min(payment_from) as demand_from, max(payment_to) as demand_upto,transaction_id FROM swm_collections group by transaction_id) cl on cl.transaction_id=t.id
                            
                                WHERE t.transaction_no ='" . $request->transactionNo . "' and t.ulb_id=" . $ulbId . " and td.id is null";


                $tran = DB::connection($this->dbConn)->select($sql);

                if ($tran) {
                    $tran = $tran[0];
                    $transactionNo = $tran->transaction_no;
                    # New Query
                    $userDtl = TblUserMstr::select('name', 'contactno')
                        ->join('tbl_user_details', 'tbl_user_details.id', 'tbl_user_mstr.user_det_id')
                        ->where('tbl_user_mstr.id', $tran->user_id)
                        ->first();
                    $tran->transaction_by = $userDtl->name ?? "";
                    $tran->contactno = $userDtl->name ?? "";

                    if ($tran->apt_id) {
                        $dmddtl = $this->GetMonthlyFee($this->dbConn, $tran->apt_id, 'Apartment', $ulbId);
                    } else {
                        $dmddtl = $this->GetMonthlyFee($this->dbConn, $tran->consumer_id, 'Consumer', $ulbId);
                    }
                    $response['transactionNo'] = (string)$transactionNo;
                    $response['transactionDate'] = date('d-m-Y', strtotime($tran->transaction_date));
                    $response['transactionAmount'] = $tran->total_payable_amt;
                    $response['transactionBy'] = $tran->transaction_by;
                    $response['consumerNo'] = $tran->consumer_no;
                    $response['consumerName'] = $tran->consumer_name;
                    $response['apartmentCode'] = $tran->apt_code;
                    $response['apartmentName'] = $tran->apt_name;
                    $response['wardNo'] = ($tran->ward_no) ? $tran->ward_no : $tran->apt_ward;
                    $response['holdingNo'] = $tran->holding_no;
                    $response['address'] = ($tran->address) ? $tran->address : $tran->apt_address;
                    $response['totalDemand'] = $tran->total_demand_amt;
                    $response['remainingAmount'] = $tran->total_remaining_amt;
                    $response['paidStatus'] = ($tran->paid_status == 1) ? "Paid" : "Pending";
                    $response['paymentMode'] = $tran->payment_mode;
                    $response['tcName'] = $tran->transaction_by;
                    $response['tcMobileNo'] = $tran->contactno;
                    $response['monthlyFee'] = $dmddtl['monthlyFee'];
                    $response['paymentTill'] = $dmddtl['paymentTill'];
                    $response['demandAmt'] = $tran->total_demand_amt;
                    $response['demandFrom'] = $tran->demand_from;
                    $response['demandUpto'] = $tran->demand_upto;
                }

                return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    // public function TransactionDeactivate(Request $request)
    // {

    //     try {

    //         $userId = $request->user()->id;
    //         $ulbId = $this->GetUlbId($userId);
    //         $status = '';
    //         $data = '';
    //         $msg = '';

    //         $validator = Validator::make($request->all(), [
    //             'transactionNo' => 'required',
    //             'receiptFile' => 'mimes:jpeg,png,jpg,png,pdf|max:1024',
    //             'remarks' => 'required'
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json(['status' => False, 'msg' => $validator->messages()]);
    //         }


    //         if (isset($request->transactionNo)) {

    //             $tran = $this->Transaction->select('id', 'paid_status', 'payment_mode')
    //                 ->where('transaction_no', $request->transactionNo)
    //                 ->where('paid_status', '!=', 0)
    //                 ->where('ulb_id', $ulbId)
    //                 ->first();

    //             if ($tran) {
    //                 if ($tran->payment_mode != "Cash" && $tran->paid_status != 2) {
    //                     $status = true;
    //                     $data = '';
    //                     $msg = "Transaction can't be deactivate because of Transaction No." . $request->transactionNo . "Cleared from bank end.";
    //                     return response()->json(['status' => true,  'msg' => $msg], 200);
    //                 } else {
    //                     $filePath = '';
    //                     if (!empty($request->receiptFile)) {
    //                         $filePath = md5($request->transactionNo) . '.' . $request->receiptFile->extension();
    //                         $request->receiptFile->move(public_path('uploads/transaction_deactivate'), $filePath);
    //                     }

    //                     $transDeactivate = $this->TransactionDeactivate;
    //                     $transDeactivate->transaction_id = $tran->id;
    //                     $transDeactivate->date = date('Y-m-d');
    //                     $transDeactivate->remarks = ($request->remarks) ? $request->remarks : "";
    //                     $transDeactivate->img_path = $filePath;
    //                     $transDeactivate->stampdate = date('Y-m-d H:i:s');
    //                     $transDeactivate->user_id = $userId;
    //                     $transDeactivate->ip_address = $request->ip();
    //                     $transDeactivate->save();

    //                     if ($transDeactivate->id > 0) {
    //                         $tran->paid_status = 0;
    //                         $tran->save();

    //                         $collection = $this->Demand->join('swm_collections', 'swm_collections.demand_id', '=', 'swm_demands.id')
    //                             ->where('swm_collections.transaction_id', $tran->id)
    //                             ->update(['paid_status' => 0, 'swm_collections.is_deactivate' => 1]);
    //                     }


    //                     $status = True;
    //                     $msg = "Deactivated Successfully";
    //                 }
    //             } else {
    //                 $status = False;
    //                 $msg = "Transaction No. not found";
    //             }
    //         } else {
    //             $status = False;
    //             $msg = "Undefined parameter supply";
    //         }
    //         return response()->json(['status' => $status, 'data' => $data, 'msg' => $msg], 200);
    //     } catch (Exception $e) {
    //         return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
    //     }
    // }

    public function TransactionDeactivate(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $ulbId = $this->GetUlbId($userId);

            $validator = Validator::make($request->all(), [
                'transactionNo' => 'required',
                'receiptFile' => 'mimes:jpeg,png,jpg,pdf|max:1024',
                'remarks' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'msg' => $validator->messages()], 400);
            }

            if (isset($request->transactionNo)) {
                $tran = $this->Transaction
                    ->select('id', 'paid_status', 'payment_mode')
                    ->where('transaction_no', $request->transactionNo)
                    ->where('paid_status', '!=', 0)
                    ->where('ulb_id', $ulbId)
                    ->first();

                if ($tran) {
                    if ($tran->payment_mode != "Cash" && $tran->paid_status != 2) {
                        $msg = "Transaction can't be deactivated because it is cleared from the bank end.";
                        return response()->json(['status' => true, 'msg' => $msg], 200);
                    } else {
                        $filePath = '';
                        if ($request->hasFile('receiptFile')) {
                            $filePath = md5($request->transactionNo) . '.' . $request->receiptFile->extension();
                            $request->receiptFile->move(public_path('uploads/transaction_deactivate'), $filePath);
                        }

                        $transDeactivate = $this->TransactionDeactivate;
                        $transDeactivate->transaction_id = $tran->id;
                        $transDeactivate->date = date('Y-m-d');
                        $transDeactivate->remarks = $request->remarks ?? "";
                        $transDeactivate->img_path = $filePath;
                        $transDeactivate->stampdate = date('Y-m-d H:i:s');
                        $transDeactivate->user_id = $userId;
                        $transDeactivate->ip_address = $request->ip();
                        $transDeactivate->save();

                        // Update transaction status
                        $this->Transaction
                            ->where('transaction_no', $request->transactionNo)
                            ->update(['paid_status' => 0]);

                        // Update related collections
                        $this->Demand
                            ->join('swm_collections', 'swm_collections.demand_id', '=', 'swm_demands.id')
                            ->where('swm_collections.transaction_id', $tran->id)
                            ->update(['paid_status' => 0, 'swm_collections.is_deactivate' => 1]);

                        $status = true;
                        $msg = "Deactivated Successfully";
                    }
                } else {
                    $status = false;
                    $msg = "Transaction No. not found";
                }
            } else {
                $status = false;
                $msg = "Undefined parameter supplied";
            }

            return response()->json(['status' => $status, 'msg' => $msg], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()], 400);
        }
    }

    public function AddRenter(Request $request)
    {

        try {

            $validator = Validator::make($request->all(), [
                'consumerId' => 'required',
                'consumerName' => 'required',
                'wardNo' => 'required',
                'mobileNo' => 'required',
                'address' => 'required',
                'pinCode' => 'required',
                'consumerCategory' => 'required',
                'consumerType' => 'required',
                'demandFrom' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }


            $apartId = Null;
            $apartName = '';
            $apartCode = '';
            $userId = $request->user()->id;
            $ulbId = $this->GetUlbId($userId);
            $response = array();

            $getConsumer = $this->Consumer->where('name', $request->consumerName)
                ->where('mobile_no', $request->mobileNo)
                ->where('consumer_category_id', $request->consumerCategory)
                ->where('consumer_type_id', $request->consumerType)
                ->where('ward_no', $request->wardNo)
                ->where('ulb_id', $ulbId)
                ->where('is_deactivate', 0)
                ->get();

            if ($getConsumer->count() == 0) {

                if (isset($request->apartmentId)) {
                    $apart = $this->Apartment->select('apt_code', 'apt_name')->where('id', $request->apartmentId)->where('ulb_id', $ulbId)->first();
                    $apartId = $request->apartmentId;
                    $apartCode = $apart->apt_code;
                    $apartName = $apart->apt_name;
                }


                $consumer = $this->Consumer;
                $consumer->ward_no = $request->wardNo;
                $consumer->apartment_id = $apartId;
                $consumer->holding_no = $request->holdingNo;
                $consumer->name = $request->consumerName;
                $consumer->mobile_no = $request->mobileNo;
                $consumer->owner_id = $request->consumerId;
                $consumer->address = $request->address;
                $consumer->firm_name = $request->firmName;
                $consumer->license_no = $request->licenseNo;
                $consumer->pincode = $request->pinCode;
                $consumer->consumer_category_id = $request->consumerCategory;
                $consumer->consumer_type_id = $request->consumerType;
                $consumer->user_id = $userId;
                $consumer->entry_date = date('Y-m-d');
                $consumer->stampdate = date('Y-m-d H:i:s');
                $consumer->is_deactivate = 0;
                $consumer->ulb_id = $ulbId;
                $consumer->save();

                $consumerUpdate = $consumer->find($consumer->id);

                $serialNo = '0001';
                $wardCreated = str_pad($request->wardNo, 2, "0", STR_PAD_LEFT);
                $consumerTypeCreated = str_pad($request->consumerType, 2, "0", STR_PAD_LEFT);
                $randCreated = str_pad($consumer->id, 5, "0", STR_PAD_LEFT);
                $consumerNo = $wardCreated . $request->consumerCategory . $consumerTypeCreated . $randCreated . $serialNo;

                $consumerUpdate->consumer_no = $consumerNo;
                $consumerUpdate->save();


                $consumerType = $this->ConsumerType->select('rate', 'name')
                    ->where('id', $request->consumerType)
                    ->first();

                $demand = $this->GenerateDemand($this->dbConn, $consumer->id, $consumerType->rate, $request->demandFrom, $userId, $ulbId);

                $response['wardNo'] = $request->wardNo;
                $response['apartmentId'] = $request->apartmentId;
                $response['holdingNo'] = $request->holdingNo;
                $response['consumerName'] = $request->consumerName;
                $response['consumerNo'] = $consumerNo;
                $response['apartmentName'] = $apartName;
                $response['mobileNo'] = $request->mobileNo;
                $response['address'] = $request->address;
                $response['firmName'] = $request->firmName;
                $response['pinCode'] = $request->pinCode;
                $response['licenseNo'] = $request->licenseNo;
                $response['consumerCategory'] = $this->ConsumerCategory->select('name')->first()->name;
                $response['consumerType'] = $consumerType->name;
                $response['demandFrom'] = $request->demandFrom;
                $response['demandDetails'] = $demand;
                $response['appliedBy'] = ($userId) ? $this->GetUserDetails($userId)->name : "";
                $response['appliedDate'] = date('Y-m-d');
                $msg = "Renter created and demand generated successfully";
            } else {
                $msg = "Renter already exist";
            }

            return response()->json(['status' => true, 'data' => $response, 'msg' => $msg], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function makePayment(Request $request)
    {

        try {
            if (isset($request->consumerId)) {
                $user   = $request->user();
                $userId = $request->userId ?? $user->id;
                $ulbId = $this->GetUlbId($userId);
                $consumerId = $request->consumerId;
                $totalPayableAmt = $request->paidAmount;
                $transcationDate = date('Y-m-d');
                $date_time = date("Y-m-d H:i:s");
                $paidUpto = date('Y-m-d', strtotime($request->paidUpto));
                $getTc = $this->GetUserDetails($userId);

                $consumer = $this->Consumer->select('swm_consumers.*', 'a.apt_name', 'a.apt_code', 'cc.name as category', 'ct.rate', 'apt_address', 'ct.name as consumer_type')
                    ->join('swm_consumer_categories as cc', 'swm_consumers.consumer_category_id', '=', 'cc.id')
                    ->join('swm_consumer_types as ct', 'swm_consumers.consumer_type_id', '=', 'ct.id')
                    ->leftjoin('swm_apartments as a', 'swm_consumers.apartment_id', '=', 'a.id')
                    ->where('swm_consumers.id', $consumerId)
                    ->where('swm_consumers.ulb_id', $ulbId)
                    ->first();

                $totalDemandAmt = $this->Demand->where('consumer_id', $consumerId)
                    ->where('paid_status', 0)
                    // ->where('is_deactivate', 0)
                    ->where('ulb_id', $ulbId)
                    ->sum('total_tax');

                $remainingAmt = $totalDemandAmt - $totalPayableAmt;


                $transcation = $this->Transaction->where('consumer_id', $consumerId)->where('ulb_id', $ulbId);

                $lastpayment = $transcation->select('total_payable_amt')->where('paid_status', '1')->orderBy('id', 'desc')->first();

                $transcation = $transcation->whereDate('transaction_date', '=', $transcationDate)
                    ->where('total_payable_amt', $totalPayableAmt)
                    ->get();
                $paidStatus = 1;

                if ($request->paymentMode == 'Cheque' || $request->paymentMode == 'Dd')
                    $paidStatus = 2;

                //if($transcation->count() == 0 && $totalPayableAmt > 0 )    
                if ($totalPayableAmt > 0) {
                    if (!$userId)
                        $userId = 0;
                    $trans = $this->Transaction;
                    $trans->transaction_date = $transcationDate;
                    $trans->total_demand_amt = $totalDemandAmt;
                    $trans->total_payable_amt = $totalPayableAmt;
                    $trans->total_remaining_amt = $remainingAmt;
                    $trans->payment_mode = $request->paymentMode;
                    $trans->paid_status = $paidStatus;
                    $trans->consumer_id = $consumerId;
                    $trans->user_id = $userId;
                    $trans->ip_address = $request->ip();
                    $trans->stampdate = $date_time;
                    $trans->ulb_id = $ulbId;
                    $trans->save();

                    if ($trans->id > 0) {
                        $trans->transaction_no = $userId . date("dmY") . $trans->id;
                        $trans->save();

                        if ($request->paymentMode == 'Cheque' || $request->paymentMode == 'Dd') {
                            $transdtls = $this->TransactionDetails;
                            $transdtls->transaction_id = $trans->id;
                            $transdtls->bank_name = $request->bankName;
                            $transdtls->branch_name = $request->branchName;
                            $transdtls->cheque_dd_no = $request->chequeNo;
                            $transdtls->cheque_dd_date = date('Y-m-d', strtotime($request->chequeDate));
                            $transdtls->save();
                        }

                        $sql = "INSERT INTO swm_collections (consumer_id, demand_id, transaction_id, total_tax, payment_from, payment_to, user_id, stampdate, ulb_id)
                        SELECT consumer_id, id, '" . $trans->id . "', total_tax, payment_from, payment_to, '" . $userId . "', '" . $date_time . "', " . $ulbId . " FROM swm_demands 
                        WHERE consumer_id='$consumerId' and (payment_to <='" . $paidUpto . "') and paid_status='0' and ulb_id=" . $ulbId;

                        DB::connection($this->dbConn)->select($sql);

                        $this->Demand->where('consumer_id', $consumerId)
                            ->where('payment_to', '<=', $paidUpto)
                            ->where('ulb_id', $ulbId)
                            ->update(['paid_status' => 1]);

                        $response['consumerName'] = $consumer->name;
                        $response['consumerCategory'] = ($consumer->category) ? $consumer->category : 'RESIDENTIAL';
                        $response['consumerType'] = $consumer->consumer_type;
                        $response['consumerNo'] = $consumer->consumer_no;
                        $response['apartmentName'] = $consumer->apt_name;
                        $response['apartmentCode'] = $consumer->apt_code;
                        $response['address'] = ($consumer->apt_address) ? $consumer->apt_address : $consumer->address;
                        $response['transactionId'] = $trans->id;
                        $response['transactionDate'] = $transcationDate;
                        $response['transactionTime'] =  date("h:i A");
                        $response['transactionNo'] = $userId . date("dmY") . $trans->id;
                        $response['holdingNo'] = $consumer->holding_no;
                        $response['mobileNo'] = $consumer->mobile_no;
                        $response['monthlyRate'] = $consumer->rate;
                        $response['demandAmount'] = $totalDemandAmt;
                        $response['receivedAmount'] = $totalPayableAmt;
                        $response['remainingAmount'] = $remainingAmt;
                        $response['paidUpto'] = $request->paidUpto;
                        $response['previousPaidAmount'] = ($lastpayment) ? $lastpayment->total_payable_amt : "0.00";
                        $response['tcName'] = $getTc->name ?? null;
                        $response['tcMobile'] = $getTc->contactno ?? null;
                        $response = array_merge($response, $this->GetUlbData($ulbId));

                        #_Whatsaap Message
                        if (strlen($consumer->mobile_no) == 10) {
                            $whatsapp2 = (Whatsapp_Send(
                                $consumer->mobile_no,
                                "all_module_payment_receipt",
                                [
                                    "content_type" => "text",
                                    [
                                        $consumer->name ?? "Consumer",
                                        $totalPayableAmt,
                                        "Consumer No.",
                                        $consumer->consumer_no,
                                        "https://deoghar.smartulb.co.in/swm/swm-payment-receipt/$trans->id"
                                    ]
                                ]
                            ));
                        }

                        return response()->json(['status' => True, 'data' => $response, 'msg' => 'Payment Done Successfully'], 200);
                    }
                }
                // else{
                //     return response()->json(['status'=> True, 'data'=>'', 'msg'=> 'This user payment today not updated..'], 200);
                // }
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function AddGeoTagging(Request $request)
    {

        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {

            $validator = Validator::make($request->all(), [
                'longitude' => 'required',
                'latitude' => 'required',
                'photo' => 'required|mimes:jpeg,png,jpg,png,pdf|max:1024',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }
            if (isset($request->consumerId) || isset($request->apartmentId)) {
                $filePath = '';
                $refId = ($request->consumerId) ? $request->consumerId : $request->apartmentId;
                $geoTagging = $this->GeoLocation;
                $geoTagging->latitude = $request->latitude;
                $geoTagging->longitude = $request->longitude;
                $geoTagging->consumer_id = ($request->consumerId) ? $request->consumerId : null;
                $geoTagging->apartment_id = ($request->apartmentId) ? $request->apartmentId : null;
                $geoTagging->user_id = $userId;
                if (!empty($request->photo)) {
                    $filePath = md5($refId) . '.' . $request->photo->extension();
                    $request->photo->move(public_path('uploads'), $filePath);

                    $geoTagging->file_name = $filePath;
                }

                $geoTagging->save();

                if ($geoTagging->id)
                    return response()->json(['status' => True, 'data' => '', 'msg' => 'GeoTagging successfully'], 200);
            } else {
                return response()->json(['status' => True, 'data' => '', 'msg' => 'Undefind parameter supply!'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function GeoLocation(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            $response = array();
            if (isset($request->consumerId) || isset($request->apartmentId)) {
                $geoLocation = $this->GeoLocation;
                if ($request->consumerId)
                    $geoLocation = $geoLocation->join('swm_consumers', 'swm_geotagging.consumer_id', '=', 'swm_consumers.id')
                        ->where('consumer_id', $request->consumerId)
                        ->where('swm_consumers.ulb_id', $ulbId);
                if ($request->apartmentId)
                    $geoLocation = $geoLocation->join('swm_apartments as a', 'swm_geotagging.apartment_id', 'a.id')
                        ->where('apartment_id', $request->apartmentId)
                        ->where('a.ulb_id', $ulbId);
                $geoLocation = $geoLocation->first();

                if ($geoLocation) {
                    $response['wardNo'] = $geoLocation->ward_no;
                    $response['consumerName'] = ($geoLocation->name) ? $geoLocation->name : "";
                    $response['consumerNo'] = ($geoLocation->consumer_no) ? $geoLocation->consumer_no : "";
                    $response['apartmentName'] = ($geoLocation->apt_name) ? $geoLocation->apt_name : "";
                    $response['apartmentCode'] = ($geoLocation->apt_code) ? $geoLocation->apt_code : "";
                    $response['latitude'] = ($geoLocation) ? $geoLocation->latitude : "";
                    $response['longitude'] = ($geoLocation) ? $geoLocation->longitude : "";
                    $response['photo'] = request()->getHttpHost() . "\\public\\" . $geoLocation->file_name;
                    return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
                } else
                    return response()->json(['status' => False, 'data' => $response, 'msg' => 'Data not found.'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function TransactionModeChange(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            if (isset($request->transactionNo) && isset($request->mode)) {
                $userId = $request->user()->id;
                $trans = $this->Transaction->where('transaction_no', $request->transactionNo)->where('ulb_id', $ulbId)->first();
                if ($trans == null) {
                    return response()->json(['status' => False, 'data' => '', 'msg' => 'No Transaction detail fond on this tranc'], 200);
                }
                $previousMode = $trans->payment_mode;
                if (($trans->payment_mode == 'Cheque' && $trans->paid_status == '2') || $trans->payment_mode == 'Cash') {
                    $trans->payment_mode = $request->mode;
                    if ($request->mode == 'Cheque' || $request->mode == 'Dd')
                        $trans->paid_status = 2;
                    else
                        $trans->paid_status = 1;


                    $modechange = $this->TransactionModeChange;
                    $modechange->transaction_id = $trans->id;
                    $modechange->date = Carbon::now();
                    $modechange->remarks = $request->remarks;
                    $modechange->ip_address = $request->ip();
                    $modechange->previous_mode = $previousMode;
                    $modechange->current_mode = $request->mode;
                    $modechange->user_id = $userId;
                    $modechange->save();

                    if ($modechange->id > 0) {
                        $trans->remarks = $request->remarks;
                        $trans->save();

                        if ($request->mode == 'Cheque' || $request->mode == 'Dd') {
                            $transdtls = $this->TransactionDetails;
                            $transdtls->transaction_id = $trans->id;
                            $transdtls->bank_name = $request->bankName;
                            $transdtls->branch_name = $request->branchName;
                            $transdtls->cheque_dd_no = $request->chequeNo;
                            $transdtls->cheque_dd_date = $request->chequeDate;
                            $transdtls->save();
                        }

                        return response()->json(['status' => True, 'data' => '', 'msg' => 'Payment Mode changed successfully'], 200);
                    }
                } else {
                    return response()->json(['status' => False, 'data' => '', 'msg' => 'Payment Mode can not change'], 200);
                }
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function AllTransaction(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            $response = array();
            if (isset($request->wardNo) && isset($request->userId)) {

                $transactions = $this->Transaction->select('transaction_date', 'swm_transactions.transaction_no', 'total_payable_amt', 'c.name', 'c.consumer_no', 'cn.name as consumer_name', 'cn.consumer_no as consumer_no1')
                    ->leftjoin('swm_consumers as c', 'swm_transactions.consumer_id', 'c.id')
                    ->leftjoin('swm_consumers as cn', 'swm_transactions.apartment_id', 'cn.apartment_id')
                    ->where('c.ward_no', $request->wardNo)
                    ->where('swm_transactions.ulb_id', $ulbId)
                    ->where('swm_transactions.user_id', $request->userId);


                if ($request->date)
                    $transactions = $transactions->whereDate('transaction_date', '=', date('Y-m-d', strtotime($request->date)));

                $transactions = $transactions->get();

                foreach ($transactions as $trans) {
                    $val['consumerName'] = $trans->name;
                    $val['Amount'] = $trans->total_payable_amt;
                    $val['transactionDate'] = date('d-m-Y', strtotime($trans->transaction_date));
                    $val['consumerNo'] = $trans->consumer_no;
                    $val['transactionNo'] = $trans->transaction_no;
                    $response[] = $val;
                }
                return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function AllCollectionSummary(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            $response = array();
            if (isset($request->userId)) {
                // $sql = "SELECT t.user_id, name, user_type, contactno, sum(total_payable_amt) as total_amt,
                // sum(CASE when payment_mode = 'Cash' then total_payable_amt else 0 end) as cash_amount,
                // sum(CASE when payment_mode = 'Cheque' then total_payable_amt else 0 end) as cheque_amount,
                // sum(CASE when payment_mode = 'Paytm' then total_payable_amt else 0 end) as paytm_amount
                // FROM swm_transactions as t
                // JOIN db_master.view_user_mstr as u on t.user_id=u.id
                // WHERE t.user_id=" . $request->userId . " and paid_status in(1,2) and t.ulb_id=" . $ulbId . " group by t.user_id";

                # New Query
                $sql = "SELECT t.user_id,
                            sum(total_payable_amt) as total_amt,
                            sum(CASE when payment_mode = 'Cash' then total_payable_amt else 0 end) as cash_amount,
                            sum(CASE when payment_mode = 'Cheque' then total_payable_amt else 0 end) as cheque_amount,
                            sum(CASE when payment_mode = 'Paytm' then total_payable_amt else 0 end) as paytm_amount
                        FROM swm_transactions as t
                            
                            WHERE t.user_id=" . $request->userId . " and paid_status in(1,2) and t.ulb_id=" . $ulbId . " group by t.user_id";

                $collection = DB::connection($this->dbConn)->select($sql);

                if ($collection) {
                    $collection = $collection[0];

                    # New Query
                    $userDtl = TblUserMstr::select('name', 'contactno', 'user_type')
                        ->join('tbl_user_details', 'tbl_user_details.id', 'tbl_user_mstr.user_det_id')
                        ->join('tbl_user_type', 'tbl_user_type.id', 'tbl_user_mstr.user_type_id')
                        ->where('tbl_user_mstr.id', $collection->user_id)
                        ->first();
                    $collection->name = $userDtl->name;
                    $collection->contactno = $userDtl->contactno;
                    $collection->user_type = $userDtl->user_type;

                    $response['tcName'] = $collection->name;
                    $response['designation'] = $collection->user_type;
                    $response['mobileNo'] = $collection->contactno;
                    $response['cash'] = $collection->cash_amount;
                    $response['cheque'] = $collection->cheque_amount;
                    $response['paytm'] = $collection->paytm_amount;
                    $response['totalAmount'] = $collection->total_amt;
                }
                return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function ConsumerUpdate(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $ulbId = $this->GetUlbId($userId);
            if (isset($request->consumerId) || isset($request->apartmentId)) {
                $consumer = $this->Consumer;
                if (isset($request->consumerId))
                    $consumer = $consumer->where('id', $request->consumerId);

                if (isset($request->apartmentId))
                    $consumer = $consumer->where('apartment_id', $request->apartmentId);

                $consumer = $consumer->first();

                $consumerLog = $this->ConsumerEditLog;
                $consumerLog->consumer_id = $consumer->id;
                $consumerLog->previous_ward_no = $consumer->ward_no;
                $consumerLog->ward_no = $consumer->ward_no;
                $consumerLog->previous_holding_no = $consumer->previous_holding_no;
                $consumerLog->holding_no = $consumer->holding_no;
                $consumerLog->name = $consumer->name;
                $consumerLog->previous_consumer_name = $consumer->name;
                $consumerLog->mobile_no = $consumer->mobile_no;
                $consumerLog->previous_mobile_no = $consumer->mobile_no;
                $consumerLog->previous_address = $consumer->address;
                $consumerLog->address = $consumer->address;
                $consumerLog->previous_license_no = $consumer->license_no;
                $consumerLog->license_no = $consumer->license_no;
                $consumerLog->previous_consumer_category_id = $consumer->consumer_category_id;
                $consumerLog->consumer_category_id = $consumer->consumer_category_id;
                $consumerLog->consumer_type_id = $consumer->consumer_type_id;
                $consumerLog->previous_consumer_type_id = $consumer->consumer_type_id;
                $consumerLog->consumer_no = $consumer->consumer_no;
                $consumerLog->previous_consumer_no = $consumer->consumer_no;
                $consumerLog->previous_pincode = $consumer->pincode;
                $consumerLog->pincode = $consumer->pincode;
                $consumerLog->user_id = $userId;
                $consumerLog->ip_address = $request->ip();
                $consumerLog->remarks = $request->remarks;
                $consumerLog->stampdate = Carbon::now();
                $consumerLog->ulb_id = $ulbId;



                $oldConsumerTypeId = $consumer->consumer_type_id;
                foreach ($request->request as $key => $value) {
                    //echo $key;
                    if (($key != 'consumerId') && ($key != 'apartmentId') && ($key != 'consumerTypeId') && ($key !=  'demandFrom')) {
                        $field_name = strtolower(preg_replace("/([^A-Z-])([A-Z])/", "$1_$2", $key));
                        $consumer->{$field_name} = $value;
                        $consumerLog->{$field_name} = $value;
                    }
                }
                $consumerLog->save();
                $consumer->save();

                if (isset($request->demandFrom) && $request->consumerTypeId != $oldConsumerTypeId) {
                    $consumer->consumer_type_id = $request->consumerTypeId;
                    $consumer->save();
                    $consumerType = $this->ConsumerType->select('rate')
                        ->where('id', $request->consumerTypeId)
                        ->first();

                    $dmddata = $this->Demand->where('consumer_id', $consumer->id)
                        ->where('paid_status', 0)
                        ->where('is_deactivate', 0)
                        ->where('ulb_id', $ulbId);

                    if ($dmddata->count() > 0) {
                        $dmddata = $dmddata->update(['is_deactivate' => 1]);
                        $this->GenerateDemand($this->dbConn, $consumer->id, $consumerType->rate, $request->demandFrom, $userId, $ulbId);
                    }
                }
                $consumer->save();

                return response()->json(['status' => True, 'data' => '', 'msg' => 'Consumer Updated Successfully'], 200);
            } else {
                return response()->json(['status' => False, 'data' => '', 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function AddCosumerReminder(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            $validator = Validator::make($request->all(), [
                'userId' => 'required',
                'reminderDate' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            if (isset($request->consumerId) || isset($request->apartmentId)) {
                $reminder = $this->CosumerReminder;
                $reminder->reference_id = ($request->consumerId) ? $request->consumerId : $request->apartmentId;
                $reminder->reference_type = ($request->consumerId) ? "Consumer" : "Apartment";
                $reminder->user_id = $request->userId;
                $reminder->reminder_date = date('Y-m-d', strtotime($request->reminderDate));
                $reminder->remarks = ($request->remarks) ? $request->remarks : "";
                $reminder->ip_address = $request->ip();
                $reminder->status = 1;
                $reminder->ulb_id = $ulbId;
                $reminder->save();

                if ($reminder->id > 0) {
                    return response()->json(['status' => True, 'data' => '', 'msg' => 'Consumer reminder added Successfully'], 200);
                }
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function GetCosumerReminder(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            if (isset($request->consumerId) || isset($request->apartmentId)) {
                $response = array();
                $reminder = $this->CosumerReminder;
                if (isset($request->consumerId))
                    $reminder = $reminder->where('reference_id', $request->consumerId)->where('reference_type', 'Consumer');
                else
                    $reminder = $reminder->where('reference_id', $request->apartmentId)->where('reference_type', 'Apartment');
                $reminder = $reminder->where('status', 1)
                    ->where('ulb_id', $ulbId)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($reminder) {
                    $response['tcName'] = ($reminder->user_id) ? $this->GetUserDetails($reminder->user_id)->name : '';
                    $response['reminderDate'] = $reminder->reminder_date;
                    $response['remarks'] = $reminder->remarks;
                    $response['ipAddress'] = $reminder->ip_address;
                    $response['createdDateTime'] = date('d-m-Y h:i A', strtotime($reminder->stampdate));

                    return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
                } else {
                    return response()->json(['status' => True, 'data' => $response, 'msg' => 'No Record Found'], 200);
                }
            } else {
                return response()->json(['status' => False, 'data' => '', 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function makeApartmentPayment(Request $request)
    {

        try {
            if (isset($request->paymentMode) && $request->paymentMode == 'Cheque') {
                $validator = Validator::make($request->all(), [
                    'chequeNo' => 'required',
                    'chequeDate' => 'required',
                    'bankName' => 'required',
                    'branchName' => 'required',
                ]);
                if ($validator->fails()) {
                    return response()->json(['status' => False, 'msg' => $validator->messages()]);
                }
            }


            if (isset($request->apartmentId) && isset($request->paymentMode)) {
                $user = $request->user();
                $userId = $user->id ?? '';
                $ulbId = $this->GetUlbId($userId);
                $apartmentId = $request->apartmentId;
                $totalPayableAmt = $request->paidAmount;
                $transcationDate = date('Y-m-d');
                $date_time = date("Y-m-d H:i:s");
                $paymentMode = $request->paymentMode;
                $paidUpto = date('Y-m-d', strtotime($request->paidUpto));
                $getTc = $this->GetUserDetails($userId);

                $totalDemandAmt = $this->Consumer->join('swm_demands as d', 'd.consumer_id', '=', 'swm_consumers.id')
                    ->where('swm_consumers.apartment_id', $apartmentId)
                    ->where('swm_consumers.ulb_id', $ulbId)
                    ->where('d.paid_status', 0)
                    ->where('d.is_deactivate', 0)
                    ->sum('d.total_tax');


                $remainingAmt = $totalDemandAmt - $totalPayableAmt;

                $transcation = $this->Transaction->where('apartment_id', $apartmentId)
                    ->where('ulb_id', $ulbId);

                $lastpayment = $transcation->select('total_payable_amt')->where('paid_status', '1')->orderBy('id', 'desc')->first();

                $transcation = $transcation->whereDate('transaction_date', '=', $transcationDate)
                    ->where('total_payable_amt', $totalPayableAmt)
                    ->get();
                $paidStatus = 1;
                $paymentFrom = date('Y') . '-01-01';
                if ($paymentMode == 'Cheque' || $paymentMode == 'Dd')
                    $paidStatus = 2;

                $response = array();

                DB::beginTransaction();
                DB::connection('db_swm')->beginTransaction();
                //if($transcation->count() == 0 && $totalPayableAmt > 0 )
                if ($totalPayableAmt > 0) {
                    if (!$userId)
                        $userId = 0;
                    $trans = $this->Transaction;
                    $trans->transaction_date = $transcationDate;
                    $trans->total_demand_amt = $totalDemandAmt;
                    $trans->total_payable_amt = $totalPayableAmt;
                    $trans->total_remaining_amt = $remainingAmt;
                    $trans->payment_mode = $paymentMode;
                    $trans->paid_status = $paidStatus;
                    $trans->apartment_id = $apartmentId;
                    $trans->consumer_id = 0;
                    $trans->user_id = $userId;
                    $trans->ip_address = $request->ip();
                    $trans->stampdate = $date_time;
                    $trans->ulb_id = $ulbId;
                    $trans->save();

                    if ($trans->id > 0) {
                        $trans->transaction_no = $userId . date("dmY") . $trans->id;
                        $trans->save();

                        if ($request->paymentMode == 'Cheque' || $paymentMode == 'Dd') {
                            $transdtls = $this->TransactionDetails;
                            $transdtls->transaction_id = $trans->id;
                            $transdtls->bank_name = $request->bankName;
                            $transdtls->branch_name = $request->branchName;
                            $transdtls->cheque_dd_no = $request->chequeNo;
                            $transdtls->cheque_dd_date = date('Y-m-d', strtotime($request->chequeDate));
                            $transdtls->save();
                        }


                        $collectionsql = "INSERT INTO swm_collections (consumer_id, demand_id, transaction_id, total_tax, payment_from, payment_to, user_id, stampdate, apartment_id, ulb_id)
                        SELECT consumer_id, d.id, '" . $trans->id . "', d.total_tax, d.payment_from, d.payment_to, '" . $userId . "', '" . $date_time . "', c.apartment_id, '" . $ulbId . "' FROM swm_demands as d
                        JOIN swm_consumers as c on d.consumer_id=c.id
                        WHERE c.apartment_id='$apartmentId' and (d.payment_to <='" . $paidUpto . "') and d.paid_status='0'";

                        DB::connection($this->dbConn)->select($collectionsql);

                        $consumerDtls = $this->Consumer->where('apartment_id', $apartmentId)
                            ->where('ulb_id', $ulbId)
                            ->where('is_deactivate', '=', 0)
                            ->get();
                        $consumerIds = collect($consumerDtls)->pluck('id');

                        $this->Demand
                            ->whereIn('consumer_id', $consumerIds)
                            ->where('payment_to', '<=', $paidUpto)
                            ->where('paid_status', '=', 0)
                            ->where('is_deactivate', '=', 0)
                            ->update(['paid_status' => 1]);

                        // $this->Consumer
                        //     ->join('swm_demands as d', 'd.consumer_id', '=', 'swm_consumers.id')
                        //     ->where('swm_consumers.apartment_id', $apartmentId)
                        //     ->where('swm_consumers.ulb_id', $ulbId)
                        //     ->where('d.payment_to', '<=', $paidUpto)
                        //     ->where('d.paid_status', '=', 0)
                        //     ->where('d.is_deactivate', '=', 0)
                        //     ->update(['d.paid_status' => 1]);

                        $sql = "SELECT a.apt_name, a.apt_code, sum(ct.rate) as monthly_rate 
                                FROM swm_apartments a
                                    join swm_consumers c on c.apartment_id=a.id
                                    join swm_consumer_types ct on c.consumer_type_id=ct.id 
                                where a.id=" . $apartmentId . " and a.ulb_id=" . $ulbId . " group by a.apt_name, a.apt_code";

                        $aprtment = DB::connection($this->dbConn)->select($sql);

                        if ($aprtment) {

                            $aprtment = $aprtment[0];
                            $response['consumerCategory'] = 'RESIDENTIAL';
                            $response['apartmentName'] = $aprtment->apt_name;
                            $response['apartmentCode'] = $aprtment->apt_code;
                            $response['transactionId'] = $trans->id;
                            $response['transactionDate'] = $transcationDate;
                            $response['transactionTime'] = Carbon::create($date_time)->format('h:i A');
                            $response['transactionNo'] = $userId . date("dmY") . $trans->id;
                            $response['monthlyRate'] = $aprtment->monthly_rate;
                            $response['demandAmount'] = $totalDemandAmt;
                            $response['receivedAmount'] = $totalPayableAmt;
                            $response['remainingAmount'] = $remainingAmt;
                            $response['paidUpto'] = $request->paidUpto;
                            $response['previousPaidAmount'] = ($lastpayment) ? $lastpayment->total_payable_amt : "0.00";
                            $response['tcName']   = $getTc->name ?? "";
                            $response['tcMobile'] = $getTc->contactno ?? "";
                        }
                        $response = array_merge($response, $this->GetUlbData($ulbId));

                        DB::commit();
                        DB::connection('db_swm')->commit();
                        return response()->json(['status' => True, 'data' => $response, 'msg' => 'Payment Done Successfully'], 200);
                    }
                }
                // else{
                //     return response()->json(['status'=> True, 'data'=>'', 'msg'=> 'This user payment today not updated..'], 200);
                // }
            } else {
                return response()->json(['status' => False, 'data' => '', 'msg' => 'Undefined parameter suppied or lack of information missing'], 200);
            }
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('db_swm')->rollBack();
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }



    public function DeactivateApartment(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            $response = array();
            if (isset($request->apartmentId)) {
                $allConsumer = $this->Consumer->where('apartment_id', $request->apartmentId)
                    ->get();

                if ($allConsumer) {

                    foreach ($allConsumer as $con) {
                        $consDtls = $this->ConsumerDeactivateDeatils->insert([
                            'consumer_id' => $con->id,
                            'remarks' => ($request->remarks) ? $request->remarks : "",
                            'deactivated_by' => $userId,
                            'deactivation_date' => date('Y-m-d'),
                            'ip_address' => $request->ip(),
                            'stampdate' => date('Y-m-d H:i:s'),
                            'ulb_id' => $ulbId
                        ]);
                        if ($consDtls) {
                            $con->is_deactivate = 1;
                            $con->save();
                            $sql = "Update swm_demands set is_deactivate=1 where consumer_id=" . $con->id . " and paid_status=0 and is_deactivate=0";
                            DB::connection($this->dbConn)->select($sql);
                        }
                    }
                }
                $this->Apartment->where('id', $request->apartmentId)->where('ulb_id', $ulbId)
                    ->update(['is_deactivate' => 1]);
                return response()->json(['status' => True, 'data' => '', 'msg' => 'Apartment Deactivated Successfully'], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function GetCaseVerificationList(Request $request)
    {
        try {
            $ulbId = $this->GetUlbId($request->user()->id);
            $response = array();
            if (isset($request->fromDate) && isset($request->toDate)) {

                $fromDate = date('Y-m-d', strtotime($request->fromDate));
                $toDate = date('Y-m-d', strtotime($request->toDate));
                $checkTc = "";
                if (isset($request->tcId))
                    $checkTc = " and t.user_id=" . $request->tcId;

                // $sql = "SELECT t.user_id, name, user_type, contactno,transaction_date,
                // sum(CASE when payment_mode = 'Cash' then total_payable_amt else 0 end) as cash_amount,
                // sum(CASE when payment_mode = 'Cheque' then total_payable_amt else 0 end) as cheque_amount,
                // sum(CASE when payment_mode = 'Dd' then total_payable_amt else 0 end) as dd_amount
                // FROM swm_transactions as t
                // left JOIN swm_transaction_verifications tv on tv.transaction_id=t.id 
                // JOIN db_master.view_user_mstr as u on t.user_id=u.id 
                // WHERE (transaction_date between '$fromDate' and '$toDate') and t.ulb_id=" . $ulbId . " and paid_status!=0 " . $checkTc . " group by t.user_id, name, user_type, contactno,transaction_date";

                # New Query
                $sql = "SELECT t.user_id, 
                t.transaction_date,
                                    SUM(CASE WHEN payment_mode = 'Cash' THEN total_payable_amt ELSE 0 END) AS cash_amount,
                                    SUM(CASE WHEN payment_mode = 'Cheque' THEN total_payable_amt ELSE 0 END) AS cheque_amount,
                                    SUM(CASE WHEN payment_mode = 'Dd' THEN total_payable_amt ELSE 0 END) AS dd_amount,
                                    SUM(CASE WHEN payment_mode = 'ONLINE' THEN total_payable_amt ELSE 0 END) AS online_amount
                            FROM swm_transactions AS t
                            LEFT JOIN swm_transaction_verifications tv ON tv.transaction_id = t.id 
                            WHERE (t.transaction_date BETWEEN '$fromDate' AND '$toDate') 
                            AND t.user_id <> 0
                            AND t.ulb_id = $ulbId
                            AND t.paid_status != 0  
                            -- AND t.payment_mode != 'ONLINE'  
                            " . $checkTc . "
                            GROUP BY 
                            t.user_id, 
                            t.transaction_date";

                $collections = DB::connection($this->dbConn)->select($sql);
                foreach ($collections as $collection) {

                    # New Query
                    $userDtls = DB::table('tbl_user_mstr')
                        ->join('tbl_user_details', 'tbl_user_details.id', 'tbl_user_mstr.user_det_id')
                        ->join('tbl_user_type', 'tbl_user_type.id', 'tbl_user_mstr.user_type_id')
                        ->where('tbl_user_mstr.id', $collection->user_id)
                        ->first();

                    $total_amt = $collection->cash_amount + $collection->cheque_amount + $collection->dd_amount + $collection->online_amount;
                    $val['tcId'] = $collection->user_id;
                    $val['tcName'] = $userDtls->name;
                    $val['designation'] = $userDtls->user_type;
                    $val['mobileNo'] = $userDtls->contactno;
                    $val['totalAmount'] = $total_amt;
                    $val['cashAmount'] = $collection->cash_amount;
                    $val['chequeAmount'] = $collection->cheque_amount;
                    $val['ddAmount'] = $collection->dd_amount;
                    $val['onlineAmount'] = $collection->online_amount; //added by al
                    $val['transactionDate'] = ($collection->transaction_date) ? date('d-m-Y', strtotime($collection->transaction_date)) : '0';
                    $response[] = $val;
                }
                return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function getCashVerificationFullDetails(Request $request)
    {
        try {

            $response = array();
            $ulbId = $this->GetUlbId($request->user()->id);
            if (isset($request->tcId) and isset($request->date)) {
                $tcId = $request->tcId;
                $date = date('Y-m-d', strtotime($request->date));

                // $sql = "SELECT c.*,a.apt_name,a.apt_code,a.ward_no as award,t.id as trans_id,t.transaction_no,t.payment_mode,total_payable_amt,tv.verify_status,tv.verify_by,verify_date,u.name as verify_by
                // FROM swm_transactions as t
                // LEFT JOIN swm_consumers c on t.consumer_id=c.id 
                // LEFT JOIN swm_apartments a on t.apartment_id=a.id
                // LEFT JOIN swm_transaction_verifications tv on tv.transaction_id=t.id 
                // LEFT JOIN db_master.view_user_mstr as u on tv.verify_by=u.id
                // WHERE t.user_id=" . $tcId . " and transaction_date='$date' and t.ulb_id=" . $ulbId . " order by t.id desc";

                # New Query
                $sql = "SELECT c.*,a.apt_name,a.apt_code,a.ward_no as award,t.id as trans_id,t.transaction_no,t.payment_mode,total_payable_amt,tv.verify_status,
                               tv.verify_by,verify_date
                            FROM swm_transactions as t
                        LEFT JOIN swm_consumers c on t.consumer_id=c.id 
                        LEFT JOIN swm_apartments a on t.apartment_id=a.id
                        LEFT JOIN swm_transaction_verifications tv on tv.transaction_id=t.id 
                        
                            WHERE t.user_id=" . $tcId . " and transaction_date='$date' and t.ulb_id=" . $ulbId . " order by t.id desc";

                $collections = DB::connection($this->dbConn)->select($sql);

                $totalCash = 0;
                $totalCheque = 0;
                $totaldd = 0;
                $totalOnline = 0; //added
                $transaction = array();
                foreach ($collections as $collection) {

                    $userDtl = TblUserMstr::select('name')
                        ->join('tbl_user_details', 'tbl_user_details.id', 'tbl_user_mstr.user_det_id')
                        ->where('tbl_user_mstr.id', $collection->verify_by)
                        ->first();
                    $collection->verify_by = $userDtl->name ?? "";

                    $coll = $this->Collections->where('transaction_id', $collection->trans_id)->orderBy('id')->get();
                    $firstrecord = collect($coll)->first();
                    $lastrecord  = collect($coll)->last();

                    if ($collection->payment_mode == 'Cash')
                        $totalCash += $collection->total_payable_amt;

                    if ($collection->payment_mode == 'Cheque')
                        $totalCheque += $collection->total_payable_amt;

                    if ($collection->payment_mode == 'DD')
                        $totalCheque += $collection->totaldd;

                    if ($collection->payment_mode == 'online') //added
                        $totalOnline += $collection->total_payable_amt;

                    $val['transactionId'] = $collection->trans_id;
                    $val['transactionNo'] = $collection->transaction_no;
                    $val['paymentMode'] = $collection->payment_mode;
                    $val['wardNo'] = ($collection->ward_no) ? $collection->ward_no : $collection->award;
                    $val['holdingNo'] = $collection->holding_no;
                    $val['consumerNo'] = $collection->consumer_no;
                    $val['consumerName'] = $collection->name;
                    $val['apartmentName'] = $collection->apt_name;
                    $val['apartmentCode'] = $collection->apt_code;
                    $val['paidAmount'] = $collection->total_payable_amt;
                    $val['paidUpto'] = '';
                    $val['verifyStatus'] = ($collection->verify_status == 1) ? 'Verified' : 'Unverified';
                    $val['verifiedBy'] = $collection->verify_by;
                    $val['verifiedOn'] = ($collection->verify_date) ? Carbon::create($collection->verify_date)->format('d-m-Y') : '';
                    $val['demandFrom'] = ($firstrecord) ? Carbon::create($firstrecord->payment_from)->format('d-m-Y') : '';
                    $val['demandUpto'] = ($firstrecord) ? Carbon::create($lastrecord->payment_to)->format('d-m-Y') : '';
                    $transaction[] = $val;
                }

                $response['transactionList'] = $transaction;
                $response['cashAmount'] = $totalCash;
                $response['chequeAmount'] = $totalCheque;
                $response['ddAmount'] = $totaldd;
                $response['onlineAmount'] = $totalOnline; //added
                $response['totalAmount'] = $totalCash + $totalCheque + $totaldd + $totalOnline; // added $totalOnline

                return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function CashVerification(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'transactionIds' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            if (isset($request->transactionIds)) {
                $userId = $request->user()->id;
                $ulbId = $this->GetUlbId($userId);
                $transactionIds = $request->transactionIds;

                foreach ($transactionIds as $trans) {
                    $t = $this->Transaction->select('total_payable_amt')
                        ->where('id', $trans)
                        ->where('ulb_id', $ulbId)
                        ->first();

                    $tverify = $this->TransactionVerification;
                    $tverify->transaction_id = $trans;
                    $tverify->verify_status = 1;
                    $tverify->verify_date = date('Y-m-d H:i:s');
                    $tverify->verify_by = $userId;
                    $tverify->ip_address = $request->ip();
                    $tverify->amount = $t->total_payable_amt;
                    $tverify->remarks = "Payment Verified";
                    $tverify->save();
                }

                return response()->json(['status' => True, 'data' => '', 'msg' => 'Verification successfully'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function getChequeDdDetails(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'transactionId' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            $transactionId = $request->transactionId;
            if (isset($transactionId)) {
                $userId = $request->user()->id;
                $ulbId = $this->GetUlbId($userId);

                $data = $this->Transaction
                    ->select(
                        'swm_transactions.id',
                        'transaction_no',
                        'transaction_date',
                        'total_demand_amt',
                        'total_payable_amt',
                        'payment_mode',
                        'bank_name',
                        'branch_name',
                        'cheque_dd_no',
                        'cheque_dd_date',
                    )
                    ->join('swm_transaction_details', 'swm_transaction_details.transaction_id', 'swm_transactions.id')
                    ->where('swm_transactions.id', $transactionId)
                    ->where('swm_transactions.ulb_id', $ulbId)
                    ->first();

                return response()->json(['status' => True, 'data' => $data, 'msg' => 'Cheque DD Details'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function ClearanceForm(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'transactionId' => 'required',
                'status' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            if (isset($request->transactionId)) {
                $userId = $request->user()->id;
                $ulbId = $this->GetUlbId($userId);
                $transactionId = $request->transactionId;
                $status = $request->status;
                $clearanceDate = isset($request->clearanceDate) ? $request->clearanceDate : '';
                $cancelationDate = isset($request->cancelationDate) ? $request->cancelationDate : '';
                $cancelationCharge = isset($request->cancelationCharge) ? $request->cancelationCharge : '';
                $reason = isset($request->reason) ? $request->reason : 'Clear';

                $reconcile_date = ($clearanceDate) ? $clearanceDate : $cancelationDate;


                $trans = $this->Transaction->find($transactionId);

                if ($trans->paid_status == 0)
                    return response()->json(['status' => True, 'data' => '', 'msg' => 'Transaction Already Deactivate!!'], 200);



                $bkcancel = $this->BankCancel;
                $bkcancel->consumer_id = $trans->consumer_id;
                $bkcancel->transaction_id = $transactionId;
                $bkcancel->remarks = $reason;
                $bkcancel->reconcilition_date = date('Y-m-d', strtotime($reconcile_date));
                $bkcancel->stampdate = date('Y-m-d H:i:s');
                $bkcancel->user_id = $userId;
                $bkcancel->ip_address = $request->ip();
                $bkcancel->apartment_id = $trans->apartment_id;
                $bkcancel->ulb_id = $ulbId;
                $bkcancel->save();

                if ($bkcancel->id && $status == 'bounce') {
                    $bkcanceldtl = $this->BankCancelDetails;
                    $bkcanceldtl->reconcile_id = $bkcancel->id;
                    $bkcanceldtl->amount = $cancelationCharge;
                    $bkcanceldtl->stampdate = date('Y-m-d H:i:s');
                    $bkcanceldtl->save();

                    if ($bkcanceldtl->id) {
                        $trans->paid_status = 3;
                        $trans->save();


                        $this->Collections->where('transaction_id', $transactionId)
                            ->where('ulb_id', $ulbId)
                            ->update(['is_deactivate' => 1]);

                        $refreance_ids = array();
                        if (empty($trans->consumer_id) || $trans->consumer_id == 0) {
                            $consumers = $this->Consumer->select('id')
                                ->where('apartment_id', $trans->apartment_id)
                                ->where('ulb_id', $ulbId)
                                ->get();
                            foreach ($consumers as $consumer)
                                $refreance_ids[] = $consumer->id;
                        } else {
                            $refreance_ids[] = $trans->consumer_id;
                        }

                        $this->Demand->join('swm_collections', 'swm_collections.demand_id', '=', 'swm_demands.id')
                            ->whereIn('swm_demands.consumer_id', $refreance_ids)
                            ->where('swm_collections.transaction_id', $transactionId)
                            ->where('swm_demands.paid_status', 1)
                            ->where('swm_demands.ulb_id', $ulbId)
                            ->update(['swm_demands.paid_status' => 0]);
                    }
                }

                if ($bkcancel->id && $status == 'clear') {
                    $trans->paid_status = 1;
                    $trans->save();
                }

                return response()->json(['status' => True, 'data' => '', 'msg' => 'Bank Reconciliation successfully'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function BankReconciliationList(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fromDate' => 'required',
                'toDate' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }
            $response = array();
            $whereparam = "";
            $ulbId = $this->GetUlbId($request->user()->id);
            $From = Carbon::create($request->fromDate)->format('Y-m-d');
            $Upto = Carbon::create($request->toDate)->format('Y-m-d');
            if (isset($request->paymentMode) && $request->paymentMode <> 'all')
                $whereparam .= "and t.payment_mode='" . ucfirst($request->paymentMode) . "'";

            if (isset($request->verificationType) && $request->verificationType != 'all') {
                if ($request->verificationType == 'pending')
                    $whereparam .= ' and reconcilition_date is null AND reconcile_id IS NULL';

                if ($request->verificationType == 'clear')
                    $whereparam .= ' and reconcile_id is null and reconcilition_date is not null';

                if ($request->verificationType == 'bounce')
                    $whereparam .= ' and reconcile_id is not null and reconcilition_date is not null';
            }



            if (isset($request->chequeNo))
                $whereparam .= "and cheque_dd_no='" . $request->chequeNo . "'";

            if (isset($request->ddNo))
                $whereparam .= "and cheque_dd_no='" . $request->ddNo . "'";

            if (isset($request->wardNo))
                $whereparam .= " and (a.ward_no='" . $request->wardNo . "' or c.ward_no='" . $request->wardNo . "') ";

            $sql = "SELECT DISTINCT t.consumer_id,t.id as trans_id, t.apartment_id,reconcile_id,reconcilition_date,transaction_no,transaction_date,payment_mode,cheque_dd_no, cheque_dd_date, bank_name,branch_name, total_payable_amt,bc.remarks,t.user_id 
            FROM  swm_transactions t
            LEFT JOIN swm_bank_reconcile bc on bc.transaction_id=t.id
            LEFT JOIN swm_bank_reconcile_details bd on bd.reconcile_id=bc.id
            LEFT JOIN swm_transaction_details td on td.transaction_id=t.id
            LEFT JOIN swm_consumers c on t.consumer_id=c.id
            LEFT JOIN swm_apartments a on t.apartment_id=a.id
            WHERE (transaction_date BETWEEN '$From' and '$Upto') and t.paid_status>0 and t.ulb_id=" . $ulbId . " " . $whereparam . " order by t.id desc";


            $transactions = DB::connection($this->dbConn)->select($sql);

            foreach ($transactions as $transaction) {
                $collection  = $this->Collections->where('transaction_id', $transaction->trans_id)->where('ulb_id', $ulbId)->orderBy('id')->get();
                $firstrecord = collect($collection)->first();
                $lastrecord  = collect($collection)->last();

                $verificationType = ($transaction->reconcile_id) ? 'Bounce' : 'Clear';

                if ($transaction->consumer_id)
                    $refdata = $this->Consumer->find($transaction->consumer_id);
                else
                    $refdata = $this->Consumer->where('apartment_id', $transaction->apartment_id)->first();

                $val['wardNo'] = $refdata->ward_no;
                $val['tranId'] = $transaction->trans_id;
                $val['tranNo'] = (string)$transaction->transaction_no;
                $val['tranDate'] = Carbon::create($transaction->transaction_date)->format('d-m-Y');
                $val['paymentMode'] = $transaction->payment_mode;
                $val['chequeNo'] = $transaction->cheque_dd_no;
                $val['chequeDate'] = ($transaction->cheque_dd_date) ? Carbon::create($transaction->cheque_dd_date)->format('d-m-Y') : '';
                $val['bankName'] = $transaction->bank_name;
                $val['branchName'] = $transaction->branch_name;
                $val['tranAmount'] = $transaction->total_payable_amt;
                $val['clearanceDate'] = ($transaction->reconcilition_date) ? Carbon::create($transaction->reconcilition_date)->format('d-m-Y') : '';
                $val['remarks'] = $transaction->remarks;
                $val['tcName'] = ($transaction->user_id) ? $this->GetUserDetails($transaction->user_id)->name : '';

                $val['verificationType'] = ($transaction->reconcilition_date) ? $verificationType : 'Pending';
                $val['demandFrom'] = ($firstrecord) ? Carbon::create($firstrecord->payment_from)->format('d-m-Y') : '';
                $val['demandUpto'] = ($firstrecord) ? Carbon::create($lastrecord->payment_to)->format('d-m-Y') : '';
                $response[] = $val;
            }
            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }





    public function ConsumerListByCategory(Request $request)
    {

        try {
            $ulbId = $this->GetUlbId($request->user()->id);
            $conArr = array();

            if (isset($request->wardNo) || isset($request->consumerCategory) || isset($request->consumertype) || isset($request->mobileNo) || isset($request->consumerName)) {

                $consumerList = $this->Consumer
                    ->select(DB::raw('swm_consumers.*, swm_consumer_categories.name as category, swm_consumer_types.name as type'))
                    ->join('swm_consumer_categories', 'swm_consumers.consumer_category_id', '=', 'swm_consumer_categories.id')
                    ->join('swm_consumer_types', 'swm_consumers.consumer_type_id', '=', 'swm_consumer_types.id')
                    ->where('swm_consumers.is_deactivate', 0);

                if (isset($request->wardNo))
                    $consumerList = $consumerList->where('swm_consumers.ward_no', $request->wardNo);

                if (isset($request->consumerCategory))
                    $consumerList = $consumerList->where('swm_consumers.consumer_category_id', $request->consumerCategory);

                if (isset($request->consumertype))
                    $consumerList = $consumerList->where('swm_consumers.consumer_type_id', $request->consumertype);

                if (isset($request->mobileNo))
                    $consumerList = $consumerList->where('swm_consumers.mobile_no', $request->mobileNo);

                if (isset($request->consumerName))
                    $consumerList = $consumerList->where('swm_consumers.name', 'ILIKE', "%$request->consumerName%");
            }

            if (isset($request->consumerNo))
                $consumerList = $this->Consumer->join('swm_consumer_categories', 'swm_consumers.consumer_category_id', '=', 'swm_consumer_categories.id')
                    ->join('swm_consumer_types', 'swm_consumers.consumer_type_id', '=', 'swm_consumer_types.id')
                    ->select(DB::raw('swm_consumers.*, swm_consumer_categories.name as category, swm_consumer_types.name as type'))
                    ->where('swm_consumers.consumer_no', $request->consumerNo)
                    ->where('swm_consumers.is_deactivate', 0);


            $consumerList = $consumerList->where('ulb_id', $ulbId)->paginate($request->perPage);
            if ($consumerList->isEmpty()) {
                throw new Exception("No consumers found");
            }
            //     $perPage = $request->perPage,
            //     $columns = ['*'],
            //     $pageName = 'consumers'
            // );

            //echo "<pre/>";print_r($consumerList);
            foreach ($consumerList as $consumer) {
                $demand = $this->Demand->where('consumer_id', $consumer->id)
                    ->where('ulb_id', $ulbId)
                    ->where('paid_status', 0)
                    ->where('is_deactivate', 0)
                    ->orderBy('id', 'asc')
                    ->get();
                $total_tax = 0.00;
                $demand_upto = '';
                $paid_status = 'Paid';
                foreach ($demand as $dmd) {
                    $total_tax += $dmd->total_tax;
                    $demand_upto = $dmd->demand_date;
                    $paid_status = 'Unpaid';
                }

                $con['id'] = $consumer->id;
                $con['wardNo'] = $consumer->ward_no;
                $con['holdingNo'] = $consumer->holding_no;
                $con['consumerName'] = $consumer->name;
                $con['apartmentId'] = $consumer->apartment_id;
                $con['consumerNo'] = $consumer->consumer_no;
                $con['Address'] = $consumer->address;
                $con['pinCode'] = $consumer->pincode;
                $con['cansumerCategory'] = $consumer->category;
                $con['cansumerType'] = $consumer->type;
                $con['mobileNo'] = $consumer->mobile_no;
                // $con['activeDemandDetails'] = $demand;
                $con['totalDemand'] = $total_tax;
                $con['demandUpto'] = $demand_upto;
                $con['paidStatus'] = $paid_status;
                $con['consumer_category_id'] = $consumer->category_id;
                $con['consumer_type_id'] = $consumer->consumer_type_id;
                $con['applyBy'] = ($consumer->user_id) ? $this->GetUserDetails($consumer->user_id)->name : '';
                $con['applyDate'] = date("d-m-Y", strtotime($consumer->entry_date));
                $con['status'] = ($consumer->is_deactivate == 0) ? 'Active' : 'Deactive';

                $conArr[] = $con;
            }
            // $data['data']        = $conArr;
            // $data['total']       = $consumerList->total();
            // $data['lastPage']    = $consumerList->lastPage();
            // $data['currentPage'] = $consumerList->currentPage();
            // $data['perPage']     = $request->perPage;
            return response()->json(['status' => True, 'data' => $conArr, 'msg' => '', 'total' => $consumerList->total(), 'lastPage' => $consumerList->lastPage()], 200);

            // else
            //     return response()->json(['status' => False, 'data' => $conArr, 'msg' => 'Undefined parameter supply'], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function PaymentDeny(Request $request)
    {
        try {
            $ulbId = $this->GetUlbId($request->user()->id);
            $validator = Validator::make($request->all(), [
                'remarks' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            if (isset($request->consumerId) || isset($request->apartmentId)) {
                $userId = $request->user()->id;
                $consumerId = $request->consumerId;
                $apartmentId = $request->apartmentId;

                if ($consumerId) {
                    $outsAmt = $this->GetDemand($this->dbConn, $consumerId, 'Consumer', $ulbId);
                } else {
                    $outsAmt = $this->GetDemand($this->dbConn, $apartmentId, 'Apartment', $ulbId);
                }

                $deny = $this->PaymentDeny;
                $deny->user_id = $userId;
                $deny->consumer_id = ($consumerId) ? $consumerId : 0;
                $deny->deny_date = date('Y-m-d H:i:s');
                $deny->is_deactivate = 0;
                $deny->outstanding_amount = $outsAmt['demandAmt'];
                $deny->denied_reason = $request->remarks;
                $deny->apartment_id = ($apartmentId) ? $apartmentId : null;
                $deny->ulb_id = $ulbId;
                $deny->latitude  = $request->latitude;
                $deny->longitude = $request->longitude;
                $deny->save();

                return response()->json(['status' => True, 'data' => '', 'msg' => 'Payment Denied successfully'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function PaymentDenyList(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $ulbId = $this->GetUlbId($userId);
            $response = array();
            if (isset($request->consumerId) || isset($request->apartmentId)) {


                $consumerId = $request->consumerId;
                $apartmentId = $request->apartmentId;

                $deny = $this->PaymentDeny->where('ulb_id', $ulbId)->where('is_deactivate', 0);
                if ($consumerId) {
                    $deny = $deny->where('consumer_id', $consumerId);
                } else {
                    $deny = $deny->where('apartment_id', $apartmentId);
                }

                $deny = $deny->get();
                foreach ($deny as $d) {
                    $val['denyBy'] = $this->GetUserDetails($d->user_id)->name;
                    $val['denyDate'] = date('d-m-Y h:i A', strtotime($d->deny_date));
                    $val['outstandingAmount'] = $d->outstanding_amount;
                    $val['remarks'] = $d->denied_reason;
                    $response[] = $val;
                }
            }
            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function GetReprintData(Request $request)
    {
        try {
            $ulbId = $this->GetUlbId($request->user()->id);
            $response = array();
            if (isset($request->transactionNo)) {
                $transactionNo = $request->transactionNo;

                $sql = "SELECT t.transaction_no,t.transaction_date,c.ward_no,c.name,c.address,a.apt_name, a.apt_code, c.consumer_no, a.apt_address, a.ward_no as apt_ward, 
                t.total_payable_amt, cl.payment_from, cl.payment_to, t.payment_mode,td.bank_name as b_name, td.branch_name, td.cheque_dd_no, td.cheque_dd_date, 
                t.total_demand_amt, t.total_remaining_amt, t.stampdate, t.apartment_id, ct.rate,cc.name as consumer_category,t.user_id, c.holding_no,c.mobile_no,ct.name as consumer_type,c.license_no
                FROM swm_transactions t
                left JOIN swm_consumers c on t.consumer_id=c.id
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
                WHERE t.transaction_no='" . $transactionNo . "'";
                DB::connection($this->dbConn)->enableQueryLog();
                $transaction = DB::connection($this->dbConn)->select($sql);

                if ($transaction) {
                    $transaction = $transaction[0];
                    $consumerCount = 0;
                    $monthlyRate = $transaction->rate;
                    if ($transaction->apartment_id) {
                        $consumer = $this->Consumer->join('swm_consumer_types as ct', 'ct.id', '=', 'swm_consumers.consumer_type_id')
                            ->where('apartment_id', $transaction->apartment_id)
                            ->where('ulb_id', $ulbId)
                            ->where('is_deactivate', 0);
                        $consumerCount = $consumer->count();
                        $monthlyRate = $consumer->sum('rate');
                    }
                    $getTc = $this->GetUserDetails($transaction->user_id);

                    $response['transactionDate'] = Carbon::create($transaction->transaction_date)->format('Y-m-d');
                    $response['transactionTime'] = Carbon::create($transaction->stampdate)->format('h:i A');
                    $response['transactionNo'] = $transaction->transaction_no;
                    $response['consumerName'] = $transaction->name;
                    $response['consumerNo'] = $transaction->consumer_no;
                    $response['mobileNo'] = $transaction->mobile_no;
                    $response['consumerCategory'] = ($transaction->consumer_category) ? $transaction->consumer_category : 'RESIDENTIAL';
                    $response['consumerType'] = $transaction->consumer_type;
                    $response['licenseNo'] = isset($transaction->license_no) ? $transaction->license_no : '';
                    $response['apartmentName'] = $transaction->apt_name;
                    $response['apartmentCode'] = $transaction->apt_code;
                    $response['ReceiptWard'] = ($transaction->apt_ward) ? $transaction->apt_ward : $transaction->ward_no;
                    $response['holdingNo'] = $transaction->holding_no;
                    $response['address'] = ($transaction->apt_address) ? $transaction->apt_address : $transaction->address;
                    $response['paidFrom'] = $transaction->payment_from;
                    $response['paidUpto'] = $transaction->payment_to;
                    $response['paymentMode'] = $transaction->payment_mode;
                    $response['bankNameReceipt'] = $transaction->b_name;
                    $response['branchName'] = $transaction->branch_name;
                    $response['chequeNo'] = $transaction->cheque_dd_no;
                    $response['chequeDate'] = $transaction->cheque_dd_date;
                    $response['noOfFlats'] = $consumerCount;
                    $response['monthlyRate'] = $monthlyRate;
                    $response['demandAmount'] = ($transaction->total_demand_amt) ? $transaction->total_demand_amt : 0;
                    $response['paidAmount'] = ($transaction->total_payable_amt) ? $transaction->total_payable_amt : 0;
                    $response['remainingAmount'] = ($transaction->total_remaining_amt) ? $transaction->total_remaining_amt : 0;
                    $response['tcName'] = $getTc->name ?? "";
                    $response['tcMobile'] = $getTc->contactno ?? "";
                }
            }
            $response = array_merge($response, $this->GetUlbData($ulbId));

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function GetReprintDatav2(Request $request)
    {
        try {

            // $ulbId = $this->GetUlbIds($request->userId);
            $ulbId = $request->ulbId ?? 11;
            $dbReceipt = "db_swm";
            $response = array();
            if (isset($request->transactionNo)) {
                $transactionNo = $request->transactionNo;

                $sql = "SELECT t.transaction_no,t.transaction_date,c.ward_no,c.name,c.address,a.apt_name, a.apt_code, c.consumer_no, a.apt_address, a.ward_no as apt_ward, 
                t.total_payable_amt, cl.payment_from, cl.payment_to, t.payment_mode,td.bank_name as b_name, td.branch_name, td.cheque_dd_no, td.cheque_dd_date, 
                t.total_demand_amt, t.total_remaining_amt, t.stampdate, t.apartment_id, ct.rate,cc.name as consumer_category,t.user_id, c.holding_no,c.mobile_no,ct.name as consumer_type,c.license_no
                FROM swm_transactions t
                left JOIN swm_consumers c on t.consumer_id=c.id
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
                WHERE t.transaction_no='" . $transactionNo . "'";
                DB::connection($dbReceipt)->enableQueryLog();
                $transaction = DB::connection($dbReceipt)->select($sql);

                if ($transaction) {
                    $transaction = $transaction[0];
                    $consumerCount = 0;
                    $monthlyRate = $transaction->rate;
                    if ($transaction->apartment_id) {
                        $consumer = $this->Consumer->join('swm_consumer_types as ct', 'ct.id', '=', 'swm_consumers.consumer_type_id')
                            ->where('apartment_id', $transaction->apartment_id)
                            ->where('ulb_id', $ulbId)
                            ->where('is_deactivate', 0);
                        $consumerCount = $consumer->count();
                        $monthlyRate = $consumer->sum('rate');
                    }
                    $getTc = $this->GetUserDetails($transaction->user_id);

                    $response['transactionDate'] = Carbon::create($transaction->transaction_date)->format('Y-m-d');
                    $response['transactionTime'] = Carbon::create($transaction->stampdate)->format('h:i A');
                    $response['transactionNo'] = $transaction->transaction_no;
                    $response['consumerName'] = $transaction->name;
                    $response['consumerNo'] = $transaction->consumer_no;
                    $response['mobileNo'] = $transaction->mobile_no;
                    $response['consumerCategory'] = ($transaction->consumer_category) ? $transaction->consumer_category : 'RESIDENTIAL';
                    $response['consumerType'] = $transaction->consumer_type;
                    $response['licenseNo'] = isset($transaction->license_no) ? $transaction->license_no : '';
                    $response['apartmentName'] = $transaction->apt_name;
                    $response['apartmentCode'] = $transaction->apt_code;
                    $response['ReceiptWard'] = ($transaction->apt_ward) ? $transaction->apt_ward : $transaction->ward_no;
                    $response['holdingNo'] = $transaction->holding_no;
                    $response['address'] = ($transaction->apt_address) ? $transaction->apt_address : $transaction->address;
                    $response['paidFrom'] = $transaction->payment_from;
                    $response['paidUpto'] = $transaction->payment_to;
                    $response['paymentMode'] = $transaction->payment_mode;
                    $response['bankNameReceipt'] = $transaction->b_name;
                    $response['branchName'] = $transaction->branch_name;
                    $response['chequeNo'] = $transaction->cheque_dd_no;
                    $response['chequeDate'] = $transaction->cheque_dd_date;
                    $response['noOfFlats'] = $consumerCount;
                    $response['monthlyRate'] = $monthlyRate;
                    $response['demandAmount'] = ($transaction->total_demand_amt) ? $transaction->total_demand_amt : 0;
                    $response['paidAmount'] = ($transaction->total_payable_amt) ? $transaction->total_payable_amt : 0;
                    $response['remainingAmount'] = ($transaction->total_remaining_amt) ? $transaction->total_remaining_amt : 0;
                    $response['tcName'] = $getTc->name ?? "";
                    $response['tcMobile'] = $getTc->contactno ?? "";
                }
            }
            $response = array_merge($response, $this->GetUlbData($ulbId));

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }



    public function GetDemandReceipt(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $ulbId = $this->GetUlbId($userId);
            $ulbData = $this->GetUlbData($ulbId);
            $response = array();
            $receipt_no = "";
            if ((isset($request->consumerId) || isset($request->apartmentId))) {

                if (isset($request->consumerId))
                    $sql = "SELECT d.consumer_id,c.name,c.consumer_no,c.ward_no,c.address,ct.rate,min(d.payment_from) as demand_from, max(d.payment_to) as demand_upto,sum(d.total_tax) as total_tax,c.mobile_no,ct.name as consumer_type,c.holding_no,c.license_no FROM swm_consumers c
                    LEFT JOIN (select * from swm_demands where paid_status=0 and is_deactivate=0) d on d.consumer_id=c.id
                    JOIN swm_consumer_types ct on c.consumer_type_id=ct.id
                    WHERE c.id=" . $request->consumerId . " and c.ulb_id=" . $ulbId . " group by c.name,c.consumer_no,c.ward_no,c.address,ct.rate,d.consumer_id,c.consumer_category_id,c.mobile_no,ct.name,c.holding_no,c.license_no";
                else
                    $sql = "WITH apartment as (
                        SELECT a.apt_code,a.apt_name,a.address,a.ward_no,min(d.payment_from) as demand_from,max(d.payment_to) as demand_upto,sum(d.total_tax) as total_tax,r.rate,r.no_of_flats FROM swm_demands d
                        JOIN (
                                select ap.id,ap.apt_code,ap.apt_name,ap.apt_address as address,ap.ward_no,c.id as consumer_id from swm_consumers c
                                JOIN swm_apartments ap on c.apartment_id=ap.id
                                WHERE ap.id=" . $request->apartmentId . "
                              ) a on d.consumer_id=a.consumer_id
                        JOIN
                        (
                            SELECT  sum(rate) as rate,apartment_id,count(c.id) as no_of_flats FROM swm_consumers c
                            JOIN swm_consumer_types ct on c.consumer_type_id=ct.id
                            WHERE apartment_id=" . $request->apartmentId . " group by apartment_id
                        ) r on r.apartment_id=a.id
                        WHERE  d.paid_status=0 and d.is_deactivate=0 and d.ulb_id=" . $ulbId . "
                        GROUP BY a.apt_code,a.apt_name,a.address,a.ward_no,rate,no_of_flats
                        )
                        select apt_code,apt_name,address,ward_no,demand_from,demand_upto,sum(total_tax) as total_tax,no_of_flats,rate from apartment
                        GROUP BY apt_code,apt_name,ward_no,demand_from,demand_upto,rate,address,no_of_flats";

                $demands = DB::connection($this->dbConn)->select($sql);

                if ($demands) {
                    $demand = $demands[0];

                    // For demand receipt log
                    $demandLog = $this->DemandLog;
                    $demandLog->amount = $demand->total_tax ?? 0;
                    if (isset($request->consumerId))
                        $demandLog->consumer_id = $request->consumerId;
                    else
                        $demandLog->apartment_id = $request->apartmentId;
                    $demandLog->printed_by = $userId;
                    $demandLog->print_datetime = Carbon::now();
                    $demandLog->ulb_id = $ulbId;
                    $demandLog->save();


                    if ($demandLog->id > 0) {
                        $receipt_no = $ulbData['shortName'] . '/' . 'SWM/' . str_pad($demandLog->id, 5, "0", STR_PAD_LEFT);
                        $demandLog->receipt_no = $receipt_no;
                        $demandLog->save();
                    }


                    $getTc = $this->GetUserDetails($userId);


                    $response['consumerName'] = isset($demand->name) ? $demand->name : '';
                    $response['consumerNo'] = isset($demand->consumer_no) ? $demand->consumer_no : '';
                    $response['mobileNo'] = isset($demand->mobile_no) ? $demand->mobile_no : '';
                    $response['holdingNo'] = isset($demand->holding_no) ? $demand->holding_no : '';
                    $response['consumerType'] = isset($demand->consumer_type) ? $demand->consumer_type : '';
                    $response['apartmentName'] = isset($demand->apt_name) ? $demand->apt_name : '';
                    $response['apartmentCode'] = isset($demand->apt_code) ? $demand->apt_code : '';
                    $response['licenseNo'] = isset($demand->license_no) ? $demand->license_no : '';
                    $response['address'] = $demand->address;
                    $response['wardNo'] = $demand->ward_no;
                    $response['demandNo'] = $receipt_no;
                    $response['demandFrom'] = $demand->demand_from;
                    $response['demandUpto'] = $demand->demand_upto;
                    $response['demandUptoPrevious'] = isset($demand->dmd2_upto) ? $demand->dmd2_upto : '';
                    $response['noOfFlats'] = isset($demand->no_of_flats) ? $demand->no_of_flats : '';
                    $response['demandAmount'] = isset($demand->total_tax) ? $demand->total_tax : 0;
                    $response['monthlyRate'] = isset($demand->rate) ? $demand->rate : 0;
                    $response['tcName'] = $getTc->name;
                    $response['tcMobile'] = $getTc->contactno;
                }
            }
            $response = array_merge($response, $ulbData);

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }




    public function DenialNotificationList(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $fromDate = $request->fromDate;
            $toDate   = $request->toDate;
            $ulbId = $this->GetUlbId($userId);
            $whereParam = "";
            $whereParam1 = "";
            $whereParam2 = "";
            $whereParam3 = "";
            $whereParam4 = "";
            if (isset($request->tcId))
                $whereParam = " and user_id=" . $request->tcId;
            if (isset($request->wardNo))
                $whereParam1 = " and ward_no=" . "'$request->wardNo'";
            if (isset($fromDate) && isset($toDate))
                $whereParam2 = " and d.deny_date BETWEEN '$fromDate 00:00:01'  AND '$toDate 23:59:59' ";
            if (isset($request->category))
                $whereParam3 = " and consumer_category_id=" . $request->category;
            if (isset($request->type))
                $whereParam4 = " and consumer_type_id=" . $request->type;
            $response = array();
            $sql = "(SELECT d.*,c.consumer_no as ref_no,c.name,c.ward_no,c.address,consumer_type_id,consumer_category_id from swm_payment_denies d 
                    JOIN(
                        SELECT max(id) as refid FROM swm_payment_denies
                        WHERE consumer_id>0 and ulb_id = " . $ulbId . " " . $whereParam . " 
                        GROUP BY consumer_id
                    ) ref on ref.refid=d.id
                    JOIN swm_consumers c on d.consumer_id=c.id " . $whereParam1 .  " " . $whereParam2 . " " . $whereParam3 . "" . $whereParam4 .   " ORDER BY deny_date desc)
                    UNION
                    (SELECT d.*,a.apt_code as ref_no,a.apt_name as name,a.ward_no,a.apt_address as address,null as consumer_type_id,null as consumer_category_id  from swm_payment_denies d 
                    JOIN(
                        SELECT max(id) as refid FROM swm_payment_denies
                        WHERE apartment_id>0 " . $whereParam . "
                        GROUP BY apartment_id
                    ) ref on ref.refid=d.id
                    JOIN swm_apartments a on d.apartment_id=a.id " . $whereParam1 . " " . $whereParam2 . "
                    ORDER BY deny_date desc)";

            $deny = DB::connection($this->dbConn)->select($sql);

            foreach ($deny as $d) {
                $val['consumerNo'] = ($d->consumer_id > 0) ? $d->ref_no : "";
                $val['consumerName'] = ($d->consumer_id > 0) ? $d->name : "";
                $val['apartmentCode'] = ($d->apartment_id > 0) ? $d->ref_no : "";
                $val['apartmentName'] = ($d->apartment_id > 0) ? $d->name : "";
                $val['wardNo'] = $d->ward_no;
                $val['address'] = $d->address;
                $val['denyBy'] = $this->GetUserDetails($d->user_id)->name;
                $val['denyDate'] = date('d-m-Y h:i A', strtotime($d->deny_date));
                $val['outstandingAmount'] = $d->outstanding_amount;
                $val['remarks'] = $d->denied_reason;
                $val['consumer_type_id'] = $d->consumer_type_id;
                $val['consumer_category_id'] = $d->consumer_category_id;
                $response[] = $val;
            }

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }



    public function PaymentAdjustment(Request $request)
    {

        try {
            $userId = $request->user()->id;
            $ulbId = $this->GetUlbId($userId);
            $validator = Validator::make($request->all(), [
                'adjustUpto' => 'required',
                'remarks' => 'required',
                'adjustAmount' => 'required',
                'billFile' => 'required|mimes:jpeg,png,jpg,png,pdf|max:1024',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }


            if (isset($request->consumerId) || isset($request->apartmentId)) {

                $dmdUpto = Carbon::create($request->adjustUpto)->format('Y-m-d');
                $dmdData = $this->Demand->select('swm_demands.*');
                if ($request->consumerId)
                    $dmdData = $dmdData->join('swm_consumers', 'swm_demands.consumer_id', '=', 'swm_consumers.id')
                        ->where('consumer_id', $request->consumerId);
                if ($request->apartmentId)
                    $dmdData = $dmdData->join('swm_consumers', 'swm_demands.consumer_id', '=', 'swm_consumers.id')
                        ->where('swm_consumers.apartment_id', $request->apartmentId);

                $dmdData = $dmdData->where('paid_status', 0)
                    ->where('swm_demands.is_deactivate', 0)
                    ->whereDate('payment_to', '<=', $dmdUpto)
                    ->where('swm_demands.ulb_id', $ulbId)
                    ->orderBy('payment_to', 'ASC')
                    ->get();

                $totDmd = $dmdData->sum('total_tax');
                if ($dmdData->count() > 0 && $totDmd == $request->adjustAmount) {
                    $dmdFrom = Carbon::create($dmdData[0]->payment_from)->format('Y-m-d');
                    $dmdAdj = $this->DemandAdjustment;
                    $dmdAdj->consumer_id  =  isset($request->consumerId) ? $request->consumerId : null;
                    $dmdAdj->apartment_id  =  isset($request->apartmentId) ? $request->apartmentId : null;
                    $dmdAdj->adjust_from  =  $dmdFrom;
                    $dmdAdj->adjust_upto  =  $dmdUpto;
                    $dmdAdj->adjust_amount = $request->adjustAmount;
                    $dmdAdj->remarks = $request->remarks;
                    $dmdAdj->user_id = $request->user()->id;
                    $dmdAdj->ip_address = $request->ip();
                    $dmdAdj->is_deactivate = 0;
                    $dmdAdj->ulb_id = $ulbId;
                    $dmdAdj->save();

                    if ($dmdAdj->id) {
                        $filePath = '';
                        if (!empty($request->billFile)) {
                            $filePath = md5(isset($request->consumerId) ? $request->consumerId : $request->apartmentId) . '.' . $request->billFile->extension();
                            $request->billFile->move(public_path('uploads/payment_adjustment'), $filePath);

                            $dmdAdj->bill_file = $filePath;
                            $dmdAdj->save();
                        }

                        foreach ($dmdData as $dmd) {
                            $dmd->paid_status = 1;
                            $dmd->save();
                        }

                        return response()->json(['status' => True, 'data' => '', 'msg' => 'Demand adjustment successfully, Payment Upto ' . $request->adjustUpto], 200);
                    }
                } else {
                    return response()->json(['status' => True, 'data' => '', 'msg' => 'Demand not adjust, because of demand amount is not same as entered amount'], 200);
                }
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function PaymentAdjustmentList(Request $request)
    {
        try {
            $ulbId = $this->GetUlbId($request->user()->id);
            $response = [];
            $fromDate = $request->fromDate ?? Carbon::now()->format('Y-m-d');
            $uptoDate = $request->uptoDate ?? Carbon::now()->format('Y-m-d');
            $perPage = $request->perPage ?? 50; // Number of records per page
            $page = $request->page ?? 1; // Current page
            $query = $this->DemandAdjustment->select(
                DB::raw('swm_demand_adjustments.adjust_from,swm_demand_adjustments.adjust_upto,swm_demand_adjustments.adjust_amount,swm_demand_adjustments.bill_file,swm_demand_adjustments.remarks,swm_demand_adjustments.user_id, c.name, c.consumer_no, c.address, c.ward_no, a.apt_name, a.apt_code, a.apt_address, a.ward_no as apt_ward, consumer_type_id, c.consumer_category_id')
            )
                ->leftjoin('swm_consumers as c', 'swm_demand_adjustments.consumer_id', 'c.id')
                ->leftjoin('swm_apartments as a', 'swm_demand_adjustments.apartment_id', 'a.id')

                ->leftjoin('swm_consumer_types as ct', 'ct.id', 'c.consumer_type_id') //added
                ->leftjoin('swm_consumer_categories as cc', 'cc.id', 'c.consumer_category_id') //added

                ->where('swm_demand_adjustments.ulb_id', $ulbId)
                ->where('swm_demand_adjustments.is_deactivate', 0)
                ->whereBetween(DB::raw('DATE(swm_demand_adjustments.stampdate)'), [$fromDate, $uptoDate]);

            // ADDED: Apply filters dynamically based on input values
            if (isset($request->wardNo) && $request->wardNo !== '') {
                $query->where('c.ward_no', $request->wardNo);
            }

            if (isset($request->consumerType) && $request->consumerType !== '') {
                $query->where('c.consumer_type_id', $request->consumerType);
            }

            if (isset($request->consumerCategory) && $request->consumerCategory !== '') {
                $query->where('c.consumer_category_id', $request->consumerCategory);
            }

            if (isset($request->tcId) && $request->tcId !== '') {
                $query->where('swm_demand_adjustments.user_id', $request->tcId);
            }


            $paymentAdjustment = $query->paginate($perPage, ['*'], 'page', $page);

            if ($paymentAdjustment->isEmpty()) {
                return response()->json(['status' => true, 'data' => [], 'msg' => 'No data found for the given filters.'], 200);
            }

            foreach ($paymentAdjustment as $adj) {
                $val = [];
                $val['consumerName'] = $adj->name;
                $val['consumerNo'] = $adj->consumer_no;
                $val['apartmentName'] = $adj->apt_name;
                $val['apartmentCode'] = $adj->apt_code;
                $val['wardNo'] = $adj->ward_no ?: $adj->apt_ward;
                $val['address'] = $adj->address ?: $adj->apt_address;
                $val['adjustFrom'] = Carbon::create($adj->adjust_from)->format('d-m-Y');
                $val['adjustUpto'] = Carbon::create($adj->adjust_upto)->format('d-m-Y');
                $val['adjustAmount'] = $adj->adjust_amount;
                $val['billFile'] = public_path('uploads/payment_adjustment') . "/" . $adj->bill_file;
                $val['remarks'] = $adj->remarks;
                $val['adjustBy'] = $this->GetUserDetails($adj->user_id)->name;
                $val['consumerType'] = $adj->consumer_type_name; // Added consumer type to response
                $response[] = $val;
            }
            return response()->json([
                'status' => true,
                'data' => [
                    'data' => $response,
                    'current_page' => $paymentAdjustment->currentPage(),
                    'total' => $paymentAdjustment->total(),
                    'per_page' => $paymentAdjustment->perPage(),
                    'last_page' => $paymentAdjustment->lastPage(),
                    'next_page_url' => $paymentAdjustment->nextPageUrl(),
                    'prev_page_url' => $paymentAdjustment->previousPageUrl(),
                ],
                'msg' => ''
            ], 200);
            return response()->json(['status' => true, 'data' => ['data' => $response], 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }
    //added searching parameter BY alok
    public function PaymentAdjustmentListOld(Request $request)
    {
        try {
            $ulbId = $this->GetUlbId($request->user()->id);
            $response = [];
            $fromDate = $request->fromDate ?? Carbon::now()->format('Y-m-d');
            $uptoDate = $request->uptoDate ?? Carbon::now()->format('Y-m-d');
    
            $query = $this->DemandAdjustment->select(
                DB::raw('swm_demand_adjustments.*, c.name, consumer_no, address, c.ward_no, apt_name, apt_code, apt_address, a.ward_no as apt_ward, c.consumer_type_id, c.consumer_category_id')
            )
                ->leftjoin('swm_consumers as c', 'swm_demand_adjustments.consumer_id', 'c.id')
                ->leftjoin('swm_apartments as a', 'swm_demand_adjustments.apartment_id', 'a.id')

                ->leftjoin('swm_consumer_types as ct', 'ct.id', 'c.consumer_type_id') //added
                ->leftjoin('swm_consumer_categories as cc', 'cc.id', 'c.consumer_category_id') //added

                ->where('swm_demand_adjustments.ulb_id', $ulbId)
                ->where('swm_demand_adjustments.is_deactivate', 0)
                ->whereBetween(DB::raw('DATE(swm_demand_adjustments.stampdate)'), [$fromDate, $uptoDate]);

            // ADDED: Apply filters dynamically based on input values
            if (isset($request->wardNo) && $request->wardNo !== '') {
                $query->where('c.ward_no', $request->wardNo);
            }

            if (isset($request->consumerType) && $request->consumerType !== '') {
                $query->where('c.consumer_type_id', $request->consumerType);
            }

            if (isset($request->consumerCategory) && $request->consumerCategory !== '') {
                $query->where('c.consumer_category_id', $request->consumerCategory);
            }

            if (isset($request->tcId) && $request->tcId !== '') {
                $query->where('swm_demand_adjustments.user_id', $request->tcId);
            }


            $paymentAdjustment = $query->get();

            if ($paymentAdjustment->isEmpty()) {
                return response()->json(['status' => true, 'data' => [], 'msg' => 'No data found for the given filters.'], 200);
            }

            foreach ($paymentAdjustment as $adj) {
                $val = [];
                $val['consumerName'] = $adj->name;
                $val['consumerNo'] = $adj->consumer_no;
                $val['apartmentName'] = $adj->apt_name;
                $val['apartmentCode'] = $adj->apt_code;
                $val['wardNo'] = $adj->ward_no ?: $adj->apt_ward;
                $val['address'] = $adj->address ?: $adj->apt_address;
                $val['adjustFrom'] = Carbon::create($adj->adjust_from)->format('d-m-Y');
                $val['adjustUpto'] = Carbon::create($adj->adjust_upto)->format('d-m-Y');
                $val['adjustAmount'] = $adj->adjust_amount;
                $val['billFile'] = public_path('uploads/payment_adjustment') . "/" . $adj->bill_file;
                $val['remarks'] = $adj->remarks;
                $val['adjustBy'] = $this->GetUserDetails($adj->user_id)->name;
                $val['consumerType'] = $adj->consumer_type_name; // Added consumer type to response
                $response[] = $val;
            }

            return response()->json(['status' => true, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }




    public function ConsumerOrApartmentList(Request $request)
    {
        $ulbId = $this->GetUlbId($request->user()->id);
        try {
            $conArr = array();
            if (isset($request->wardNo) || isset($request->name)) {
                $apartmentList = $this->Apartment->select(DB::raw("id,ward_no, apt_name as name, apt_code as ref_no,apt_address as address, 'Apartment' as category, '' as type, '' as mobile_no, is_deactivate"));
                if (isset($request->name))
                    $apartmentList = $apartmentList->where('apt_name', 'like', '%' . $request->name . '%');
                $apartmentList = $apartmentList->where('ward_no', $request->wardNo)
                    ->where('ulb_id', $ulbId)
                    ->orderBy('id', 'desc');

                $consumerData = $this->Consumer->leftjoin('swm_consumer_categories', 'swm_consumers.consumer_category_id', '=', 'swm_consumer_categories.id')
                    ->join('swm_consumer_types', 'swm_consumers.consumer_type_id', '=', 'swm_consumer_types.id')
                    ->select(DB::raw('swm_consumers.id, ward_no, swm_consumers.name as name, consumer_no as ref_no,address, swm_consumer_categories.name as category, swm_consumer_types.name as type, mobile_no, is_deactivate'))
                    ->where('swm_consumers.ulb_id', $ulbId)
                    ->where('ward_no', $request->wardNo);
                if (isset($request->name))
                    $consumerData = $consumerData->where('swm_consumers.name', 'like', '%' . $request->name . '%');
                if (isset($request->consumerType))
                    $consumerData = $consumerData->where('swm_consumers.consumer_type_id', $request->consumerType);

                $consumerData = $consumerData->orderBy('id', 'desc');

                if (isset($request->buildingType) && $request->buildingType == 'apartment') {
                    $consumerList = $apartmentList->paginate(100);
                } elseif (isset($request->buildingType) && $request->buildingType == 'indivisual') {
                    $consumerList = $consumerData->paginate(100);
                } else {
                    $consumerList = $consumerData->union($apartmentList)->paginate(100);
                }


                //echo "<pre/>";print_r($consumerList);
                foreach ($consumerList as $consumer) {
                    if ($consumer->category == 'Apartment')
                        $demand = $this->GetDemand($this->dbConn, $consumer->id, 'Apartment', $ulbId);
                    else
                        $demand = $this->GetDemand($this->dbConn, $consumer->id, 'Consumer', $ulbId);
                    //

                    $con['id'] = $consumer->id;
                    $con['wardNo'] = $consumer->ward_no;
                    $con['consumerName'] = ($consumer->category != 'Apartment') ? $consumer->name : "";
                    $con['consumerNo'] = ($consumer->category != 'Apartment') ? $consumer->ref_no : "";
                    $con['apartmentName'] = ($consumer->category == 'Apartment') ? $consumer->name : "";
                    $con['apartmentCode'] = ($consumer->category == 'Apartment') ? $consumer->ref_no : "";
                    $con['Address'] = $consumer->address;
                    $con['cansumerCategory'] = $consumer->category;
                    $con['cansumerType'] = $consumer->type;
                    $con['mobileNo'] = $consumer->mobile_no;
                    $con['outstandingDemand'] = $demand['demandAmt'];
                    $con['demandFrom'] = $demand['demandFrom'];
                    $con['demandUpto'] = $demand['demandUpto'];
                    $con['paidStatus'] = ($demand) ? "Unpaid" : "Paid";
                    $con['status'] = ($consumer->is_deactivate == 0) ? 'Active' : 'Deactive';

                    $conArr[] = $con;
                }
                return response()->json(['status' => True, 'data' => $conArr, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $conArr, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function GetReminderList(Request $request)
    {
        $todayDate = Carbon::now();
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        $wardNo = $request->wardNo;
        $fromDate = $request->fromDate;
        $toDate   = $request->toDate;
        $consumerCategory = $request->category;
        $consumerType     = $request->type;
        try {

            $response = array();
            DB::enableQueryLog();
            $reminders = $this->CosumerReminder->select(DB::raw('max(c.id) as cid, max(a.id) as aid, max(reminder_date) as reminder_date, max(remarks) as remarks,
                                            max(swm_consumer_reminders.stampdate) as stampdate, max(swm_consumer_reminders.user_id) as user_id,name, 
                                            apt_name, apt_code, consumer_no, c.ward_no, a.ward_no as apt_ward, address, apt_address,
                                            consumer_category_id,consumer_type_id,
                                            reference_type,swm_consumer_reminders.id'))
                ->leftjoin('swm_consumers as c', function ($join) {
                    $join->on('swm_consumer_reminders.reference_id', '=', 'c.id')
                        ->where('reference_type', '=', 'Consumer')
                        ->where('c.is_deactivate', 0);
                })
                ->leftjoin('swm_apartments as a', function ($join1) {
                    $join1->on('swm_consumer_reminders.reference_id', '=', 'a.id')
                        ->where('reference_type', '=', 'Apartment')
                        ->where('a.is_deactivate', 0);
                });
            $reminders = $reminders->where('status', 1)
                ->where('swm_consumer_reminders.ulb_id', $ulbId);

            if (isset($fromDate) && isset($toDate))
                $reminders = $reminders->whereBetween('reminder_date', [$fromDate, $toDate]);
            if (isset($request->tcId))
                $reminders = $reminders->where('swm_consumer_reminders.user_id', $request->tcId);
            if (isset($consumerCategory))
                $reminders = $reminders->where('consumer_category_id', $consumerCategory);
            if (isset($consumerType))
                $reminders = $reminders->where('consumer_type_id', $consumerType);
            if (isset($wardNo))
                $reminders = $reminders
                    ->where(function ($query) use ($wardNo) {
                        $query->where("c.ward_no", $wardNo)
                            ->orWhere(function ($query) use ($wardNo) {
                                $query->where("a.ward_no", $wardNo);
                            });
                    });

            // if(isset($wardNo))
            // $reminders = $reminders->where('c.ward_no', $wardNo)
            //     ->orWhere('a.ward_no', $wardNo); //<-----------here
            $reminders = $reminders->orderBy('swm_consumer_reminders.id', 'desc')
                ->groupBy([
                    'name',
                    'c.consumer_type_id',
                    'c.consumer_category_id',
                    'apt_name',
                    'apt_code',
                    'consumer_no',
                    'c.ward_no',
                    'a.ward_no',
                    'address',
                    'apt_address',
                    'reference_type',
                    'swm_consumer_reminders.id'
                ])
                ->get();

            //print_r($reminders);
            foreach ($reminders as $reminder) {
                $user = $this->GetUserDetails($reminder->user_id);
                $val['id'] = ($reminder->cid) ? $reminder->cid : $reminder->aid;
                $val['tcName'] = ($user) ? $user->name : '';
                $val['wardNo'] = ($reminder->ward_no) ? $reminder->ward_no : $reminder->apt_ward;
                $val['address'] = ($reminder->address) ? $reminder->address : $reminder->apt_address;
                $val['consumerName'] = ($reminder->reference_type == 'Consumer') ? $reminder->name : "";
                $val['consumerNo'] = ($reminder->reference_type == 'Consumer') ? $reminder->consumer_no : '';
                $val['apartmentName'] = ($reminder->reference_type == 'Apartment') ? $reminder->apt_name : "";
                $val['apartmentCode'] = ($reminder->reference_type == 'Apartment') ? $reminder->apt_code : "";
                $val['reminderDate'] = date('d-m-Y', strtotime($reminder->reminder_date));
                $val['remarks'] = $reminder->remarks;
                $val['consumer_category_id'] = $reminder->consumer_category_id;
                $val['consumer_type_id'] = $reminder->consumer_type_id;
                $val['date'] = date('d-m-Y', strtotime($reminder->stampdate));
                $response[] = $val;
            }
            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function tcReminderList(Request $request)
    {
        $todayDate = Carbon::now();
        $userId    = $request->user()->id;
        $ulbId     = $this->GetUlbId($userId);
        $response  = array();
        try {

            $todayReminderList = $this->CosumerReminder
                ->whereDate('reminder_date', $todayDate)
                ->where('user_id', $userId)
                ->get();

            $consumerList      = collect($todayReminderList)->where('reference_type', 'Consumer');
            $apartmentList     = collect($todayReminderList)->where('reference_type', 'Apartment');

            $consumerIds      = collect($consumerList)->pluck('reference_id');
            $apartmentIds     = collect($apartmentList)->pluck('reference_id');


            // foreach ($todayReminderList as $reminder) {
            //     if ($reminder->reference_type == 'Consumer') {
            //         $consumerList = $this->Consumer->leftjoin('swm_consumer_categories', 'swm_consumers.consumer_category_id', '=', 'swm_consumer_categories.id')
            //             ->join('swm_consumer_types', 'swm_consumers.consumer_type_id', '=', 'swm_consumer_types.id')
            //             ->select(DB::raw('swm_consumers.id, ward_no, swm_consumers.name as name, consumer_no as ref_no,address, swm_consumer_categories.name as category, swm_consumer_types.name as type, mobile_no'))
            //             ->where('swm_consumers.ulb_id', $ulbId)
            //             ->where('swm_consumers.id', $reminder->reference_id)
            //             ->where('is_deactivate', 0)
            //             ->orderBy('id', 'desc')
            //             ->get();
            //     }
            // }

            $apartmentList = $this->Apartment
                ->select(DB::raw("swm_apartments.id,ward_no, apt_name as name, apt_code as ref_no,apt_address as address, 'Apartment' as category, '' as type, '' as mobile_no,swm_consumer_reminders.remarks"))
                ->join('swm_consumer_reminders', 'swm_consumer_reminders.reference_id', 'swm_apartments.id')
                ->whereIn('swm_apartments.id', $apartmentIds)
                ->where('swm_apartments.ulb_id', $ulbId)
                ->where('is_deactivate', 0)
                ->whereDate('reminder_date', $todayDate)
                ->orderBy('swm_apartments.id', 'desc')
                ->get();

            $consumerList = $this->Consumer
                ->select(DB::raw('swm_consumers.id, ward_no, swm_consumers.name as name, consumer_no as ref_no,address, swm_consumer_categories.name as category, swm_consumer_types.name as type, mobile_no,swm_consumer_reminders.remarks'))
                ->leftjoin('swm_consumer_categories', 'swm_consumers.consumer_category_id', '=', 'swm_consumer_categories.id')
                ->join('swm_consumer_types', 'swm_consumers.consumer_type_id', '=', 'swm_consumer_types.id')
                ->join('swm_consumer_reminders', 'swm_consumer_reminders.reference_id', 'swm_consumers.id')
                ->where('swm_consumers.ulb_id', $ulbId)
                ->whereIn('swm_consumers.id', $consumerIds)
                ->where('is_deactivate', 0)
                ->whereDate('reminder_date', $todayDate)
                ->orderBy('id', 'desc')
                ->get();

            $consumerList = $consumerList->merge($apartmentList);

            foreach ($consumerList as $consumer) {
                if ($consumer->category == 'Apartment')
                    $demand = $this->GetDemand($this->dbConn, $consumer->id, 'Apartment', $ulbId);
                else
                    $demand = $this->GetDemand($this->dbConn, $consumer->id, 'Consumer', $ulbId);

                $con['id']                = $consumer->id;
                $con['wardNo']            = $consumer->ward_no;
                $con['consumerName']      = ($consumer->category != 'Apartment') ? $consumer->name : "";
                $con['consumerNo']        = ($consumer->category != 'Apartment') ? $consumer->ref_no : "";
                $con['apartmentName']     = ($consumer->category == 'Apartment') ? $consumer->name : "";
                $con['apartmentCode']     = ($consumer->category == 'Apartment') ? $consumer->ref_no : "";
                $con['Address']           = $consumer->address;
                $con['cansumerCategory']  = $consumer->category;
                $con['cansumerType']      = $consumer->type;
                $con['mobileNo']          = $consumer->mobile_no;
                $con['reminder']          = $consumer->remarks;
                $con['outstandingDemand'] = $demand['demandAmt'];
                $con['demandFrom']        = $demand['demandFrom'];
                $con['demandUpto']        = $demand['demandUpto'];
                $con['paidStatus']        = ($demand['demandAmt'] > 0) ? "Unpaid" : "Paid";

                $response[] = $con;
            }

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function ConsumerPastTransactions(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $ulbId = $this->GetUlbId($userId);
            $response = array();
            if (isset($request->consumerId) || isset($request->apartmentId)) {
                $allTrans = $this->Transaction->select('swm_transactions.*', 'swm_consumers.ward_no', 'consumer_no', 'name', 'a.apt_code', 'a.apt_name', 'a.ward_no as apt_ward')
                    ->leftjoin('swm_consumers', 'swm_transactions.consumer_id', '=', 'swm_consumers.id')
                    ->leftjoin('swm_transaction_deactivates', 'swm_transaction_deactivates.transaction_id', '=', 'swm_transactions.id')
                    ->leftjoin('swm_apartments as a', 'swm_transactions.apartment_id', '=', 'a.id')
                    ->where('swm_transactions.ulb_id', $ulbId)
                    ->whereNotIn('swm_transactions.id', function ($query) {
                        $query->select('transaction_id')->from('swm_transaction_deactivates');
                    });
                if (isset($request->consumerId))
                    $allTrans = $allTrans->where('swm_transactions.consumer_id', $request->consumerId);

                if (isset($request->apartmentId))
                    $allTrans = $allTrans->where('swm_transactions.apartment_id', $request->apartmentId);



                $allTrans = $allTrans->orderBy('swm_transactions.id', 'desc')->paginate(10);

                foreach ($allTrans as $trans) {
                    $collection = $this->Collections->where('transaction_id', $trans->id);
                    $firstrecord = $collection->orderBy('id', 'asc')->first();
                    $lastrecord = $collection->latest('id')->first();


                    $getuserdata = $this->GetUserDetails($trans->user_id);
                    $val['id'] = ($trans->consumer_id) ? $trans->consumer_id : $trans->apartment_id;

                    $val['wardNo'] = ($trans->ward_no) ? $trans->ward_no : $trans->apt_ward;
                    $val['consumerNo'] = $trans->consumer_no;
                    $val['consumerName'] = $trans->name;
                    $val['apartmentCode'] = $trans->apt_code;
                    $val['apartmentName'] = $trans->apt_name;
                    $val['transactionNo'] = (string)$trans->transaction_no;
                    $val['mode'] = $trans->payment_mode;
                    $val['transactionDate'] = Carbon::create($trans->transaction_date)->format('d-m-Y');
                    $val['amount'] = $trans->total_payable_amt;
                    $val['demandFrom'] = ($firstrecord) ? Carbon::create($firstrecord->payment_from)->format('d-m-Y') : '';
                    $val['demandUpto'] = ($lastrecord) ? Carbon::create($lastrecord->payment_to)->format('d-m-Y') : '';
                    $val['tcName'] = $getuserdata->name ?? "";
                    $val['paidStatus'] = $trans->paid_status;
                    $response[] = $val;
                }

                return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
            } else {
                return response()->json(['status' => False, 'data' => $response, 'msg' => 'Undefined parameter supply'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    /**
     * | Generate order id
     */
    public function generateOrderId(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "amount"        => "required|numeric",
            "consumerId"    => "required|int",
            // "consumerType"  => "nullable|in:commercial,independent,apartment",
            "consumerType"  => "nullable|in:consumer,apartment",
        ]);

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);

        try {
            $user         = auth()->user();
            $mRazorpayReq = new RazorpayReq();
            $consumerType = $req->consumerType;
            $apartmentId     = null;
            $consumerId      = null;

            if ($consumerType == 'apartment') {
                $consumerDetails = $this->Apartment->where('id', $req->consumerId)->first();
                $apartmentId     = $consumerDetails->id;
            } else {
                $consumerDetails = $this->Consumer->where('id', $req->consumerId)->first();
                $consumerId      = $consumerDetails->id;
            }

            if (!$consumerDetails)
                throw new Exception("Consumer Not Found");

            // $orderData = $api->order->create(array('amount' => $req->amount * 100, 'currency' => 'INR',));
            $orderId = time() . $user->id;

            $mReqs = [
                "order_id"       => $orderId,
                "payment_type"   => $consumerType,
                "consumer_id"    => $consumerId,
                "apartment_id"   => $apartmentId,
                "user_id"        => $user->id,
                "amount"         => $req->amount,
                "ulb_id"         => $consumerDetails->ulb_id,
                "ip_address"     => $this->getClientIpAddress()
            ];
            $data = $mRazorpayReq->store($mReqs);
            $response = [
                'order_id'      => $orderId,
                'consumer_id'   => $req->consumerId,
                'consumer_type' => $req->consumerType,
            ];

            return $this->responseMsgs(true, "Order Id Details", $response);
        } catch (Exception $e) {
            return $this->responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], "");
        }
    }

    /**
     * | Save Order Response
     */
    public function saveOrderResponse(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "orderId"       => "required|numeric",
            "consumerId"    => "required|int",
            "consumerType"  => "nullable|in:consumer,apartment",
            "amount"        => "required",
            "payUpto"       => "required",
        ]);

        if ($validator->fails())
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->first(),
                'errors' => "Validation Error"
            ], 200);
        try {
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
                        'userId' => $req->userId
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

    // public function addTcComplain(Request $request)
    // {

    //     try {
    //         $userId = $request->user()->id;
    //         $ulbId = $this->GetUlbId($userId);
    //         $validator = Validator::make($request->all(), [
    //             'complain' => 'required|MIN:5',
    //         ]);
    //         if ($validator->fails()) {
    //             return response()->json(['status' => False, 'msg' => $validator->messages()]);
    //         }

    //         $complain = $this->TcComplaint;
    //         $complain->user_id   =  $userId;
    //         $complain->complain  =  $request->complain;
    //         $complain->complain_date  =  Carbon::now();
    //         $complain->ulb_id  =  $ulbId;
    //         $complain->save();

    //         return response()->json(['status' => True, 'data' => '', 'msg' => 'Your complain save successfully'], 200);
    //     } catch (Exception $e) {
    //         return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
    //     }
    // }

    public function addTcComplain(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $ulbId = $this->GetUlbId($userId);
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

            $complain = $this->TcComplaint;
            $complain->user_id        =  $userId;
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

            return response()->json(['status' => True, 'data' => $complain->complain_no, 'msg' => 'Your complain save successfully and complain no is ' . $complain->complain_no], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    // public function getTcComplain(Request $request)
    // {
    //     $userId = $request->user()->id;
    //     $ulbId = $this->GetUlbId($userId);
    //     try {
    //         $response = array();
    //         $records = $this->TcComplaint
    //             ->where('is_deactivate', 0)
    //             ->where('ulb_id', $ulbId);
    //         if (isset($request->tcId))
    //             $records = $records->where('user_id', $request->tcId);
    //         $records = $records->orderBy('id', 'DESC')
    //             ->paginate(1000);

    //         foreach ($records as $record) {
    //             $getuserdata = $this->GetUserDetails($record->user_id);
    //             $val['tcName'] = $getuserdata->name;
    //             $val['complain'] = $record->complain;
    //             $val['date'] = Carbon::create($record->complain_date)->format('d-m-Y');
    //             $response[] = $val;
    //         }

    //         return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
    //     } catch (Exception $e) {
    //         return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
    //     }
    // }

    public function getTcComplain(Request $request)
    {
        $userId  = $request->user()->id;
        $perPage = $request->perPage ?? 10;
        $ulbId   = $this->GetUlbId($userId);
        try {

            $response = array();
            $records = $this->TcComplaint
                ->where('is_deactivate', 0)
                ->where('ulb_id', $ulbId);

            if (isset($request->fromDate) && isset($request->toDate))
                $records = $records->whereBetween('complain_date', [$request->fromDate, $request->toDate]);

            if (isset($request->tcId))
                $records = $records->where('user_id', $request->tcId);

            if (isset($request->search))
                $records = $records->where('complain_no', $request->search);

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

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    // public function getTcComplainV2(Request $request)
    // {
    //     $userId  = $request->user()->id;
    //     $perPage = $request->perPage ?? 10;
    //     $ulbId   = $this->GetUlbId($userId);
    //     try {

    //         $response = array();
    //         $records = $this->TcComplaint
    //             ->where('is_deactivate', 0)
    //             ->where('ulb_id', $ulbId);

    //         if (isset($request->fromDate) && isset($request->uptoDate))
    //             $records = $records->whereBetween('complain_date', [$request->fromDate, $request->uptoDate]);

    //         if (isset($request->tcId))
    //             $records = $records->where('user_id', $request->tcId);

    //         if (isset($request->complainNo))
    //             $records = $records->where('complain_no', $request->complainNo);

    //         if (isset($request->wardNo))
    //             $records = $records->where('ward_no', $request->wardNo);

    //         $records = $records->orderBy('id', 'DESC')
    //             ->paginate($perPage);

    //         foreach ($records as $record) {
    //             $getuserdata = $this->GetUserDetails($record->user_id);
    //             $tldata      = $this->GetUserDetails($record->tl_id);
    //             $val['id']            = $record->id;
    //             $val['ward_no']       = $record->ward_no;
    //             $val['consumer_name'] = $record->consumer_name;
    //             $val['latitude']      = $record->latitude;
    //             $val['longitude']     = $record->longitude;
    //             $val['address']       = $record->address;
    //             $val['complain']      = $record->complain;
    //             $val['complain_no']   = $record->complain_no;
    //             $val['tcName']        = $getuserdata->name ?? "Citizen";
    //             $val['tlName']        = $tldata->name ?? "";
    //             $val['date']          = Carbon::create($record->complain_date)->format('d-m-Y');
    //             $response[] = $val;
    //         }

    //         $data['data'] = $response;
    //         $data['current_page'] = $records->currentPage();
    //         $data['last_page']    = $records->lastPage();
    //         $data['total']        = $records->total();
    //         $data['per_page']     = $perPage;

    //         return response()->json(['status' => True, 'data' => $data, 'msg' => ''], 200);
    //     } catch (Exception $e) {
    //         return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
    //     }
    // }


    # 
    public function getTcComplainV2(Request $request)
    {
        $userId  = $request->user()->id;
        $perPage = $request->perPage ?? 10;
        $ulbId   = $this->GetUlbId($userId);

        try {
            $response = array();
            $records = $this->TcComplaint
                ->where('is_deactivate', 0)
                ->where('ulb_id', $ulbId);

            if (array_key_exists('citizenId', $request->all())) {
                $records = $records->where(function ($query) {
                    $query->whereNull('user_id')
                        ->orWhere('user_id', '0');
                });
            } else {
                if (isset($request->fromDate) && isset($request->uptoDate)) {
                    $records = $records->whereBetween('complain_date', [$request->fromDate, $request->uptoDate]);
                }

                if (isset($request->tcId) && $request->tcId !== '') {
                    $records = $records->where('user_id', $request->tcId);
                }

                if (isset($request->complainNo) && $request->complainNo !== '') {
                    $records = $records->where('complain_no', $request->complainNo);
                }

                if (isset($request->wardNo) && $request->wardNo !== '') {
                    $records = $records->where('ward_no', $request->wardNo);
                }
            }
            if (isset($request->fromDate) && isset($request->uptoDate)) {
                $records = $records->whereBetween('complain_date', [$request->fromDate, $request->uptoDate]);
            }

            if (isset($request->wardNo) && $request->wardNo !== '') {
                $records = $records->where('ward_no', $request->wardNo);
            }

            $records = $records->orderBy('id', 'DESC')->paginate($perPage);

            foreach ($records as $record) {
                $getuserdata = $this->GetUserDetails($record->user_id);
                $tldata      = $this->GetUserDetails($record->tl_id);

                $val['id']            = $record->id;
                $val['ward_no']       = $record->ward_no;
                $val['consumer_name'] = $record->consumer_name;
                $val['latitude']      = $record->latitude;
                $val['longitude']     = $record->longitude;
                $val['address']       = $record->address;
                $val['complain']      = $record->complain;
                $val['complain_no']   = $record->complain_no;
                $val['tcName']        = $getuserdata->name ?? "Citizen";
                $val['tlName']        = $tldata->name ?? "";
                $val['date']          = Carbon::create($record->complain_date)->format('d-m-Y');
                $response[] = $val;
            }

            $data['data'] = $response;
            $data['current_page'] = $records->currentPage();
            $data['last_page']    = $records->lastPage();
            $data['total']        = $records->total();
            $data['per_page']     = $perPage;

            return response()->json(['status' => true, 'data' => $data, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function getComplainDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'    => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => False, 'msg' => $validator->messages()->first(), 'data' => ""]);
        }

        $user       = $request->user();
        $userTypeId = $user->user_type_id;
        // $ulbId = $this->GetUlbId($userId);
        try {
            $docUrl  = "https://deoghar.smartulb.co.in/swm-deoghar";
            // $docUrl  = "http://172.18.1.131:6969";
            $record = $this->TcComplaint
                ->where('is_deactivate', 0)
                // ->where('ulb_id', $ulbId)
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
            $val['tcName']        = $getuserdata->name ?? "Citizen";
            $val['date']          = Carbon::create($record->complain_date)->format('d-m-Y');
            $val['is_tl']         = $userTypeId == 4 ? true : false;

            return response()->json(['status' => True, 'data' => $val, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function switchStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'    => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => False, 'msg' => $validator->messages()->first(), 'data' => ""]);
        }

        $user = $request->user();
        try {
            $record = $this->TcComplaint
                ->where('status', 3)
                ->where('id', $request->id)
                ->first();

            if ($record) {
                if ($user->user_type_id == 4) {
                    $record->update(['status' => 2]);
                }
            }
            return response()->json(['status' => True, 'data' => "", 'msg' => "Status Updated"], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function complainResolved(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'       => 'required|numeric',
            'photo'    => 'required|mimes:png,jpeg,jpg',
            'remarks'  => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => False, 'msg' => $validator->messages()->first(), 'data' => ""]);
        }

        $user = $request->user();
        try {
            $complain = $this->TcComplaint
                ->where('status', 2)
                ->where('id', $request->id)
                ->first();
            if (!$complain)
                return response()->json(['status' => False, 'msg' => "No Data Found", 'data' => ""]);

            if ($user->user_type_id == 4) {
                if (!empty($request->photo)) {
                    $randomNumber  = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
                    $filePath = md5($randomNumber) . '.' . $request->photo->extension();
                    $request->photo->move(public_path('uploads'), $filePath);

                    $complain->tl_photo   = $filePath;
                    $complain->tl_remarks = $request->remarks;
                    $complain->tl_id      = $user->id;
                    $complain->status     = 1;
                    $complain->save();
                }
            }

            #_Whatsaap Message
            if (strlen($complain->mobile_no) == 10) {
                $whatsapp2 = (Whatsapp_Send(
                    $complain->mobile_no,
                    "all_module_resolved_complain",
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


            return response()->json(['status' => True, 'data' => "", 'msg' => "Status Updated"], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function addRoute(Request $request)
    {

        try {
            $userId = $request->user()->id;
            $ulbId = $this->GetUlbId($userId);
            $validator = Validator::make($request->all(), [
                'routeName' => 'required|MIN:5',
                'selectedDate' => 'required',

            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }
            $getConsumers = "";
            $getApartments = "";
            $getConsumersArr = array();
            $getApartmentsArr = array();
            $selectedDate = Carbon::create($request->selectedDate)->format('Y-m-d');
            if (isset($request->selectedDate)) {
                $transConsumers = $this->Transaction->select('consumer_id')
                    ->where('consumer_id', '>', 0)
                    ->whereDate('transaction_date', '=', $selectedDate)
                    ->get();

                foreach ($transConsumers as $transConsumer)
                    $getConsumersArr[] = $transConsumer->consumer_id;
                $getConsumers = implode(",", $getConsumersArr);
                $transApartments = $this->Transaction->select('apartment_id')
                    ->where('apartment_id', '>', 0)
                    ->whereDate('transaction_date', '=', $selectedDate)
                    ->get();

                foreach ($transApartments as $transApartment)
                    $getApartmentsArr[] = $transApartment->apartment_id;
                $getApartments = implode(",", $getApartmentsArr);
            }
            $getRoutes = $this->Routes->where('route_name', $request->routeName)->where('is_deactivate', 0)->count();
            if (($getConsumers || $getApartments) && $getRoutes == 0) {
                $complain = $this->Routes;
                $complain->route_name  =  $request->routeName;
                $complain->route_date  =  $selectedDate;
                $complain->consumer_ids  =  $getConsumers;
                $complain->apartment_ids  =  $getApartments;
                $complain->user_id  =  $userId;
                $complain->ulb_id  =  $ulbId;
                $complain->save();
            } else {
                return response()->json(['status' => True, 'data' => '', 'msg' => 'Route already register, please change route name'], 200);
            }
            return response()->json(['status' => True, 'data' => '', 'msg' => 'Route set successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function RouteList(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId = $this->GetUlbId($userId);
        try {
            $response = array();
            $records = $this->Routes
                ->where('ulb_id', $ulbId)
                ->where('is_deactivate', 0);
            if (isset($userId))
                $records = $records->where('user_id', $userId);
            $records = $records->orderBy('id', 'DESC')
                ->paginate(1000);

            foreach ($records as $record) {
                $apt = explode(',', $record->apartment_ids);
                $cons = explode(',', $record->consumer_ids);
                $totalConsumerCount = count($apt) + count($cons);
                $getuserdata = $this->GetUserDetails($record->user_id);

                $val['id'] = $record->id;
                $val['tcName'] = $getuserdata->name;
                $val['routeName'] = $record->route_name;
                $val['totalConsumerCount'] = $totalConsumerCount;
                $response[] = $val;
            }

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function RouteDataById(Request $request)
    {
        $userId = $request->user()->id;
        $ulbId  = $this->GetUlbId($userId);
        $apt    = "";
        $cons   = "";
        $apartmentList   = collect();
        $consumerList    = collect();
        try {
            $response = array();
            if (isset($request->routeId)) {
                $record = $this->Routes
                    ->where('id', $request->routeId)
                    ->first();

                if ($record->apartment_ids)
                    $apt  = explode(',', $record->apartment_ids);
                if ($record->consumer_ids)
                    $cons = explode(',', $record->consumer_ids);

                if ($apt)
                    $apartmentList = $this->Apartment->select(DB::raw("id,ward_no, apt_name as name, apt_code as ref_no,apt_address as address, 'Apartment' as category, '' as type, '' as mobile_no"))
                        ->whereIn('id', $apt)
                        ->where('ulb_id', $ulbId)
                        ->where('is_deactivate', 0)
                        ->orderBy('id', 'desc')
                        ->get();

                if ($cons)
                    $consumerList = $this->Consumer->leftjoin('swm_consumer_categories', 'swm_consumers.consumer_category_id', '=', 'swm_consumer_categories.id')
                        ->join('swm_consumer_types', 'swm_consumers.consumer_type_id', '=', 'swm_consumer_types.id')
                        ->select(DB::raw('swm_consumers.id, ward_no, swm_consumers.name as name, consumer_no as ref_no,address, swm_consumer_categories.name as category, swm_consumer_types.name as type, mobile_no'))
                        ->where('swm_consumers.ulb_id', $ulbId)
                        ->whereIn('swm_consumers.id', $cons)
                        ->where('is_deactivate', 0)
                        ->orderBy('id', 'desc')
                        ->get();

                $consumerList = $consumerList->merge($apartmentList);

                foreach ($consumerList as $consumer) {
                    if ($consumer->category == 'Apartment')
                        $demand = $this->GetDemand($this->dbConn, $consumer->id, 'Apartment', $ulbId);
                    else
                        $demand = $this->GetDemand($this->dbConn, $consumer->id, 'Consumer', $ulbId);

                    $con['id'] = $consumer->id;
                    $con['wardNo'] = $consumer->ward_no;
                    $con['consumerName'] = ($consumer->category != 'Apartment') ? $consumer->name : "";
                    $con['consumerNo'] = ($consumer->category != 'Apartment') ? $consumer->ref_no : "";
                    $con['apartmentName'] = ($consumer->category == 'Apartment') ? $consumer->name : "";
                    $con['apartmentCode'] = ($consumer->category == 'Apartment') ? $consumer->ref_no : "";
                    $con['Address'] = $consumer->address;
                    $con['cansumerCategory'] = $consumer->category;
                    $con['cansumerType'] = $consumer->type;
                    $con['mobileNo'] = $consumer->mobile_no;
                    $con['outstandingDemand'] = $demand['demandAmt'];
                    $con['demandFrom'] = $demand['demandFrom'];
                    $con['demandUpto'] = $demand['demandUpto'];
                    $con['paidStatus'] = ($demand['demandAmt'] > 0) ? "Unpaid" : "Paid";

                    $response[] = $con;
                }

                $msg = "";
            } else {
                $msg = "Undefind parameter supply";
            }
            return response()->json(['status' => True, 'data' => $response, 'msg' => $msg], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function updateRoute(Request $request)
    {

        try {
            $userId = $request->user()->id;
            $ulbId = $this->GetUlbId($userId);
            $validator = Validator::make($request->all(), [
                'routeId' => 'required',
                'routeName' => 'required|MIN:5',

            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            $getRoute = $this->Routes->find($request->routeId);
            $getRoute->route_name = $request->routeName;

            if (isset($request->action)) {
                $apt = explode(',', $getRoute->apartment_ids);
                $cons = explode(',', $getRoute->consumer_ids);

                if ($request->action == 'add' && $request->consumerId)
                    $cons[] = $request->consumerId;
                if ($request->action == 'remove' && $request->consumerId) {
                    if (($key = array_search($request->consumerId, $cons)) !== false) {
                        unset($cons[$key]);
                    }
                }

                if ($request->action == 'add' && $request->apartmentId)
                    $apt[] = $request->apartmentId;
                if ($request->action == 'remove' && $request->apartmentId) {
                    if (($key = array_search($request->apartmentId, $apt)) !== false) {
                        unset($apt[$key]);
                    }
                }

                $getRoute->consumer_ids = implode(",", $cons);
                $getRoute->apartment_ids = implode(",", $apt);
            }
            $getRoute->save();

            return response()->json(['status' => True, 'data' => '', 'msg' => 'Data Updated successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function AnalyticDashboardData(Request $request)
    {

        $user = Auth()->user();
        $ulbId = $user->current_ulb;
        $userId = $user->id;

        try {
            $response = array();

            if (isset($request->fromDate) && isset($request->toDate)) {

                $From = Carbon::create($request->fromDate)->format('Y-m-d');
                $Upto = Carbon::create($request->toDate)->format('Y-m-d');

                $whereParam = "";
                if (isset($request->wardNo))
                    $whereParam = " and ward_no=" . $request->wardNo;

                if (isset($request->category))
                    $whereParam .= " and consumer_category_id=" . $request->category;

                if (isset($request->tcId))
                    $whereParam .= " and user_id=" . $request->tcId;

                //Consumer Details
                $sql = "WITH
                Consumer AS (
                    SELECT count(*) as total_consumer,
                                count(CASE WHEN consumer_category_id = 1 THEN id end) as residential,
                                count(CASE WHEN consumer_category_id != 1 THEN id end) as commercial
                    FROM swm_consumers where (entry_date between '" . $From . "' and '" . $Upto . "') and is_deactivate=0 and ulb_id=" . $ulbId . " " . $whereParam . "
                ),
                TotalDmd AS (
                    select sum(total_tax) as outstanding_amount FROM swm_demands
                    LEFT JOIN swm_consumers on swm_demands.consumer_id=swm_consumers.id " . $whereParam . "
                    WHERE (payment_to between '" . $From . "' and '" . $Upto . "')  and swm_demands.is_deactivate=0 and swm_demands.ulb_id=" . $ulbId . " and paid_status=0
                ),
                AdjustAmt AS (
                    select sum(adjust_amount) as adjust_amount from swm_demand_adjustments 
                    LEFT JOIN swm_consumers on swm_demand_adjustments.consumer_id=swm_consumers.id " . $whereParam . "
                    where swm_demand_adjustments.is_deactivate=0 and swm_demand_adjustments.ulb_id=" . $ulbId . " and (date(swm_demand_adjustments.stampdate) between '" . $From . "' and '" . $Upto . "')
                )
                SELECT total_consumer,residential,commercial,outstanding_amount,adjust_amount
                FROM  Consumer,TotalDmd,AdjustAmt";


                $Report = DB::connection($this->dbConn)->select($sql);
                if ($Report)
                    $Report = $Report[0];

                // Demand Details
                $sqldemand = "SELECT EXTRACT(YEAR FROM payment_to) AS year, 
                                     EXTRACT(MONTH FROM payment_to) AS month,
                                     sum(total_tax) as value  
                              FROM swm_demands 
                              LEFT JOIN swm_consumers on swm_demands.consumer_id=swm_consumers.id " . $whereParam . "
                                WHERE (payment_to between '" . $From . "' and '" . $Upto . "') 
                                and swm_demands.is_deactivate=0 and swm_demands.ulb_id=" . $ulbId . "
                                GROUP BY EXTRACT(YEAR FROM payment_to), EXTRACT(MONTH FROM payment_to)";

                $totalDmds = DB::connection($this->dbConn)->select($sqldemand);

                $total_demand = 0;
                foreach ($totalDmds as $dmd) {
                    $total_demand += $dmd->value;
                }

                // Arrear Details
                $sqlarrear = "SELECT EXTRACT(YEAR FROM transaction_date) as year, EXTRACT(MONTH FROM transaction_date) as month,sum(total_payable_amt) as value
                FROM swm_transactions
                LEFT JOIN swm_transaction_deactivates on swm_transaction_deactivates.transaction_id=swm_transactions.id
                LEFT JOIN swm_consumers on swm_transactions.consumer_id=swm_consumers.id " . $whereParam . "
                WHERE swm_transactions.ulb_id=" . $ulbId . " and swm_transaction_deactivates.transaction_id is null and swm_transactions.paid_status!=0 and transaction_date < '" . $From . "'
                GROUP BY EXTRACT(YEAR FROM transaction_date), EXTRACT(MONTH FROM transaction_date)";

                $totalarrears = DB::connection($this->dbConn)->select($sqlarrear);

                // Collection Details
                $sqlcollection = "SELECT EXTRACT(YEAR FROM transaction_date) as year, EXTRACT(MONTH FROM transaction_date) as month,sum(total_payable_amt) as value,
                sum(CASE WHEN paid_status = 1 and paid_status !=0 THEN total_payable_amt END) as total_collection,
                sum(CASE WHEN paid_status = 2 THEN total_payable_amt END) as total_reconcile_pending_amount
                FROM swm_transactions
                LEFT JOIN swm_transaction_deactivates on swm_transaction_deactivates.transaction_id=swm_transactions.id
                LEFT JOIN swm_consumers on swm_transactions.consumer_id=swm_consumers.id " . $whereParam . "
                WHERE swm_transactions.ulb_id=" . $ulbId . " and swm_transaction_deactivates.transaction_id is null and swm_transactions.paid_status!=0 and (transaction_date between '" . $From . "' and '" . $Upto . "')
                GROUP BY EXTRACT(YEAR FROM transaction_date), EXTRACT(MONTH FROM transaction_date)";

                $totalcolls = DB::connection($this->dbConn)->select($sqlcollection);

                $total_collection = 0;
                $total_reconcile = 0;
                foreach ($totalcolls as $coll) {
                    $total_collection += $coll->total_collection;
                    $total_reconcile += $coll->total_reconcile_pending_amount;
                }



                $response['totalDemand'] = $total_demand ?? 0;
                $response['outstandingDemand'] = $Report->outstanding_amount ?? 0;
                $response['totalConsumer'] = $Report->total_consumer ?? 0;
                $response['totalCollection'] = $total_collection ?? 0;
                $response['reconcilePending'] = $total_reconcile ?? 0;
                $response['adjustmentAmount'] = $Report->adjust_amount ?? 0;
                $response['totalResidenstialConsumer'] = $Report->residential ?? 0;
                $response['totalCommercialConsumer'] = $Report->commercial ?? 0;
                $response['demand'] = $totalDmds;
                $response['collection'] = $totalcolls;
                $response['arrear'] = $totalarrears;
            }

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function AnalyticDashboardData2(Request $request)
    {
        $currentMonth  = Carbon::now()->format('m');
        $currentYear   = Carbon::now()->format('Y');
        $startOfMonth  = Carbon::parse($request->fromDate)->startOfMonth()->toDateString();
        $endOfMonth    = Carbon::parse($request->toDate)->endOfMonth()->toDateString();
        $user   = Auth()->user();
        $ulbId  = $user->current_ulb;
        $userId = $user->id;

        try {
            $response = array();

            # Total Consumer
            $sql = "SELECT count(*) as total_consumer,
                          count(CASE WHEN consumer_category_id = 1 THEN id end) as residential,
                          count(CASE WHEN consumer_category_id != 1 THEN id end) as commercial
                    FROM swm_consumers 
                    where is_deactivate = 0";
            $consumerdtls = DB::connection($this->dbConn)->select($sql);
            $consumerdtls = collect($consumerdtls)->first();

            # New Total Consumer
            $sql = "SELECT count(*) as total_consumer,
                          count(CASE WHEN consumer_category_id = 1 THEN id end) as residential,
                          count(CASE WHEN consumer_category_id != 1 THEN id end) as commercial
                    FROM swm_consumers 
                    where is_deactivate = 0
                    AND   entry_date between '" . $startOfMonth . "' and '" . $endOfMonth . "'";

            $newconsumerdtls = DB::connection($this->dbConn)->select($sql);
            $newconsumerdtls = collect($newconsumerdtls)->first();

            # Current Demand
            $sql = "SELECT 
                            sum(total_tax) as current_demand
                    FROM  swm_demands
                    WHERE is_deactivate = 0
                    AND   payment_from between '" . $startOfMonth . "' and '" . $endOfMonth . "'";

            $currentDemand = DB::connection($this->dbConn)->select($sql);
            $currentDemand = collect($currentDemand)->first();


            # Arrear Demand
            $sql = "SELECT 
                            sum(total_tax) as arrear_demand
                    FROM  swm_demands
                    WHERE is_deactivate = 0
                    AND   paid_status   = 0
                    AND   EXTRACT(MONTH FROM payment_from) < $currentMonth
                    AND   EXTRACT(YEAR FROM payment_from)  < $currentYear";
            $arrearDemand = DB::connection($this->dbConn)->select($sql);
            $arrearDemand = collect($arrearDemand)->first();


            $response['totalConsumer']             = $consumerdtls->total_consumer ?? 0;
            $response['totalResidenstialConsumer'] = $consumerdtls->residential ?? 0;
            $response['totalCommercialConsumer']   = $consumerdtls->commercial ?? 0;
            $response['currentMonthTotalConsumer']            = $newconsumerdtls->total_consumer ?? 0;
            $response['currentMonthTotalResidentialConsumer'] = $newconsumerdtls->residential ?? 0;
            $response['currentMonthTotalCommercialConsumer']  = $newconsumerdtls->commercial ?? 0;
            $response['currentDemand']                        = $currentDemand->current_demand ?? 0;
            $response['arrearDemand']                         = $arrearDemand->arrear_demand ?? 0;
            $response['totalDemand']                          = $arrearDemand->arrear_demand + $currentDemand->current_demand;

            //  $this->responseMsgs(true, "You Got Late", $response);



            $From = Carbon::parse($request->fromDate)->startOfMonth()->toDateString();
            $Upto = Carbon::parse($request->toDate)->endOfMonth()->toDateString();

            # Old Query 
            $sqlcollection = "SELECT 
                                    sum(total_payable_amt) as value,
                                    sum(CASE WHEN paid_status = 1 and paid_status !=0 THEN total_payable_amt END) as total_collection,
                                    sum(CASE WHEN paid_status = 2 THEN total_payable_amt END) as total_reconcile_pending_amount
                            FROM swm_transactions
                            LEFT JOIN swm_transaction_deactivates on swm_transaction_deactivates.transaction_id=swm_transactions.id
                            WHERE swm_transactions.ulb_id=" . $ulbId . " 
                            and swm_transaction_deactivates.transaction_id is null 
                            and swm_transactions.paid_status!=0
                            and (transaction_date between '" . $startOfMonth . "' and '" . $endOfMonth . "')";

            $current_totalcolls = DB::connection($this->dbConn)->select($sqlcollection);

            # Total Collection
            $sqlcollection = "SELECT 
                                    sum(total_payable_amt) as value,
                                    sum(CASE WHEN paid_status = 1 and paid_status !=0 THEN total_payable_amt END) as total_collection,
                                    sum(CASE WHEN paid_status = 2 THEN total_payable_amt END) as total_reconcile_pending_amount
                            FROM swm_transactions
                            LEFT JOIN swm_transaction_deactivates on swm_transaction_deactivates.transaction_id=swm_transactions.id
                            WHERE swm_transactions.ulb_id=" . $ulbId . " 
                            and swm_transaction_deactivates.transaction_id is null 
                            and swm_transactions.paid_status!=0";

            $overAllcolls = DB::connection($this->dbConn)->select($sqlcollection);
            $overAllcolls = collect($overAllcolls)->first();


            if (isset($request->fromDate) && isset($request->toDate)) {

                $From = Carbon::create($request->fromDate)->startOfMonth()->toDateString();
                $Upto = Carbon::create($request->toDate)->endOfMonth()->toDateString();

                $whereParam = "";
                if (isset($request->wardNo))
                    $whereParam = " and ward_no=" . $request->wardNo;

                if (isset($request->category))
                    $whereParam .= " and consumer_category_id=" . $request->category;

                if (isset($request->tcId))
                    $whereParam .= " and user_id=" . $request->tcId;

                //Consumer Details
                $sql = "WITH
                Consumer AS (
                    SELECT count(*) as total_consumer,
                                count(CASE WHEN consumer_category_id = 1 THEN id end) as residential,
                                count(CASE WHEN consumer_category_id != 1 THEN id end) as commercial
                    FROM swm_consumers where (entry_date between '" . $From . "' and '" . $Upto . "') and is_deactivate=0 and ulb_id=" . $ulbId . " " . $whereParam . "
                ),
                TotalDmd AS (
                    select sum(total_tax) as outstanding_amount FROM swm_demands
                    LEFT JOIN swm_consumers on swm_demands.consumer_id=swm_consumers.id " . $whereParam . "
                    WHERE (payment_to between '" . $From . "' and '" . $Upto . "')  and swm_demands.is_deactivate=0 and swm_demands.ulb_id=" . $ulbId . " and paid_status=0
                ),
                AdjustAmt AS (
                    select sum(adjust_amount) as adjust_amount from swm_demand_adjustments 
                    LEFT JOIN swm_consumers on swm_demand_adjustments.consumer_id=swm_consumers.id " . $whereParam . "
                    where swm_demand_adjustments.is_deactivate=0 and swm_demand_adjustments.ulb_id=" . $ulbId . " and (date(swm_demand_adjustments.stampdate) between '" . $From . "' and '" . $Upto . "')
                )
                SELECT total_consumer,residential,commercial,outstanding_amount,adjust_amount
                FROM  Consumer,TotalDmd,AdjustAmt";


                $Report = DB::connection($this->dbConn)->select($sql);
                if ($Report)
                    $Report = $Report[0];

                // Demand Details
                $sqldemand = "SELECT EXTRACT(YEAR FROM payment_to) AS year, 
                                     EXTRACT(MONTH FROM payment_to) AS month,
                                     sum(total_tax) as value  
                              FROM swm_demands 
                              LEFT JOIN swm_consumers on swm_demands.consumer_id=swm_consumers.id " . $whereParam . "
                                WHERE (payment_to between '" . $From . "' and '" . $Upto . "') 
                                and swm_demands.is_deactivate=0 and swm_demands.ulb_id=" . $ulbId . "
                                GROUP BY EXTRACT(YEAR FROM payment_to), EXTRACT(MONTH FROM payment_to)";

                $totalDmds = DB::connection($this->dbConn)->select($sqldemand);

                $total_demand = 0;
                foreach ($totalDmds as $dmd) {
                    $total_demand += $dmd->value;
                }

                // Arrear Details
                $sqlarrear = "SELECT EXTRACT(YEAR FROM transaction_date) as year, EXTRACT(MONTH FROM transaction_date) as month,sum(total_payable_amt) as value
                FROM swm_transactions
                LEFT JOIN swm_transaction_deactivates on swm_transaction_deactivates.transaction_id=swm_transactions.id
                LEFT JOIN swm_consumers on swm_transactions.consumer_id=swm_consumers.id " . $whereParam . "
                WHERE swm_transactions.ulb_id=" . $ulbId . " and swm_transaction_deactivates.transaction_id is null and swm_transactions.paid_status!=0 and transaction_date < '" . $From . "'
                GROUP BY EXTRACT(YEAR FROM transaction_date), EXTRACT(MONTH FROM transaction_date)";

                $totalarrears = DB::connection($this->dbConn)->select($sqlarrear);

                // Collection Details
                $sqlcollection = "SELECT 
                                        EXTRACT(YEAR FROM transaction_date) as year,
                                        EXTRACT(MONTH FROM transaction_date) as month,
                                        sum(total_payable_amt) as value,
                                        sum(CASE WHEN paid_status = 1 and paid_status !=0 THEN total_payable_amt END) as total_collection,
                                        sum(CASE WHEN paid_status = 2 THEN total_payable_amt END) as total_reconcile_pending_amount
                                FROM swm_transactions
                                LEFT JOIN swm_transaction_deactivates on swm_transaction_deactivates.transaction_id=swm_transactions.id
                                LEFT JOIN swm_consumers on swm_transactions.consumer_id=swm_consumers.id " . $whereParam . "
                                        WHERE swm_transactions.ulb_id=" . $ulbId . " and swm_transaction_deactivates.transaction_id is null and swm_transactions.paid_status!=0 and (transaction_date between '" . $From . "' and '" . $Upto . "')
                                        GROUP BY EXTRACT(YEAR FROM transaction_date), EXTRACT(MONTH FROM transaction_date)";

                $totalcolls = DB::connection($this->dbConn)->select($sqlcollection);

                $currentCollection = 0;
                $total_reconcile = 0;
                foreach ($totalcolls as $coll) {
                    $currentCollection += $coll->total_collection;
                    $total_reconcile += $coll->total_reconcile_pending_amount;
                }


                // $response['totalDemand'] = $total_demand ?? 0;
                $response['outstandingDemand'] = $Report->outstanding_amount ?? 0;
                $response['totalCollection']   = round($overAllcolls->value) ?? 0;
                $response['currentCollection']   = $currentCollection ?? 0;
                $response['totalBalance']      = (($arrearDemand->arrear_demand + $currentDemand->current_demand) - round($overAllcolls->value)) ?? 0;
                // $response['currentBalance']    = ($currentDemand->current_demand - $currentCollection) ?? 0;
                $response['reconcilePending'] = $total_reconcile ?? 0;
                $response['adjustmentAmount'] = $Report->adjust_amount ?? 0;
                $response['demand'] = $totalDmds;
                $response['collection'] = $totalcolls;
                $response['arrear'] = $totalarrears;

                // $response['totalConsumer'] = $Report->total_consumer ?? 0;
                // $response['totalResidenstialConsumer'] = $Report->residential ?? 0;
                // $response['totalCommercialConsumer'] = $Report->commercial ?? 0;
                $response['totalConsumer']             = $consumerdtls->total_consumer ?? 0;
                $response['totalResidenstialConsumer'] = $consumerdtls->residential ?? 0;
                $response['totalCommercialConsumer']   = $consumerdtls->commercial ?? 0;

                $response['currentMonthTotalConsumer']            = $newconsumerdtls->total_consumer ?? 0;
                $response['currentMonthTotalResidentialConsumer'] = $newconsumerdtls->residential ?? 0;
                $response['currentMonthTotalCommercialConsumer']  = $newconsumerdtls->commercial ?? 0;
            }

            return response()->json(['status' => True, 'data' => $response, 'msg' => ''], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }


    public function DeleteRoute(Request $request)
    {
        try {
            $response = array();
            if (isset($request->routeId)) {
                $record = $this->Routes
                    ->where('id', $request->routeId)
                    ->update(['is_deactivate' => 1]);


                $msg = "Route deleted successfully";
            } else {
                $msg = "Undefind parameter supply";
            }
            return response()->json(['status' => True, 'data' => $response, 'msg' => $msg], 200);
        } catch (Exception $e) {
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function DefaultConsumerAdd(Request $request)
    {

        try {
            $user = Auth()->user();
            $ulbId = $user->ulb_id;
            $userId = $user->id;
            $validator = Validator::make($request->all(), [
                'wardNo' => 'required',
                'aptName' => 'required',
                'aptCode' => 'required',
                'aptAddress' => 'required',
                'pinCode' => 'required',
                'noOfFlat' => 'required|int',
                'consumerCategory' => 'required|int',
                'consumerType' => 'required|int',
                'demandFrom' => 'required|date'
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator->messages()]);
            }

            $checkApartment = $this->Apartment->where('apt_name', $request->aptName)
                ->where('apt_code', $request->aptCode)
                ->where('ulb_id', $ulbId)
                ->count();
            DB::beginTransaction();
            if ($checkApartment == 0) {
                $apartment = $this->Apartment;
                $apartment->ward_no  =  $request->wardNo;
                $apartment->apt_name  =  $request->aptName;
                $apartment->apt_code  =  $request->aptCode;
                $apartment->apt_address  =  $request->aptAddress;
                $apartment->pincode  =  $request->pinCode;
                $apartment->ulb_id  =  $ulbId;
                $apartment->is_deactivate  =  0;
                $apartment->save();

                if (isset($apartment->id) && $apartment->id > 0) {

                    for ($i = 1; $i <= $request->noOfFlat; $i++) {

                        $consumer_id = $this->createCon($request, $i);

                        $consumerUpdate = $this->Consumer->find($consumer_id);

                        //Check Consumer for that apartment
                        $getConsum = $this->Consumer->select('consumer_no')->where('apartment_id', $apartment->id)->where('ulb_id', $ulbId);
                        $oldConsumerNo = $getConsum->first();
                        if ($getConsum->count() > 0) {
                            $apartCount = $getConsum->count() + 1;
                            $consumerNo = substr($oldConsumerNo->consumer_no, 0, 10) . str_pad($apartCount, 5, "0", STR_PAD_LEFT);
                        } else {
                            $serialNo = '0001';
                            $wardCreated = str_pad($request->wardNo, 2, "0", STR_PAD_LEFT);
                            $consumerTypeCreated = str_pad($request->consumerType, 2, "0", STR_PAD_LEFT);
                            $randCreated = str_pad($consumer_id, 5, "0", STR_PAD_LEFT);

                            $consumerNo = $wardCreated . $request->consumerCategory . $consumerTypeCreated . $randCreated . $serialNo;
                        }

                        $consumerUpdate->apartment_id = $apartment->id;
                        $consumerUpdate->consumer_no = $consumerNo;
                        $consumerUpdate->save();

                        $consumerType = $this->ConsumerType->select('rate', 'name')
                            ->where('id', $request->consumerType)
                            ->first();
                        //Generate Demand
                        $demand = $this->GenerateDemand($this->dbConn, $consumer_id, $consumerType->rate, $request->demandFrom, $userId, $ulbId);
                    }
                }
                DB::commit();
                $msg = "Default Consumer created and their demand generated successfully";
            } else {
                $msg = "Apartment already exist.";
            }
            return response()->json(['status' => true, 'data' => array(), 'msg' => $msg], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => False, 'data' => '', 'msg' => $e->getMessage()], 400);
        }
    }

    public function createCon(Request $request, $counter)
    {
        $user = Auth()->user();
        $ulbId = $user->ulb_id;
        $userId = $user->id;

        $consumer = new Consumer();
        $consumer->setConnection($this->dbConn);
        $consumer->ward_no = $request->wardNo;
        $consumer->holding_no = null;
        $consumer->name = $request->aptName . "(Consumer-" . $counter . ")";
        $consumer->mobile_no = null;
        $consumer->address = $request->aptAddress;
        $consumer->firm_name = null;
        $consumer->pincode = $request->pinCode;
        $consumer->consumer_category_id = $request->consumerCategory;
        $consumer->consumer_type_id = $request->consumerType;
        $consumer->license_no = null;
        $consumer->user_id = $userId;
        $consumer->entry_date = date('Y-m-d');
        $consumer->stampdate = date('Y-m-d H:i:s');
        $consumer->is_deactivate = 0;
        $consumer->ulb_id = $ulbId;
        $consumer->is_default = 1;
        $consumer->save();

        return $consumer->id;
    }

    /* 
    * | This will return the DEOGHAR NAGAR NIGAM ULB Details 
    * | From `tbl_ulb_list` table
    * | 
    * | Created Date : 21-02-2025
    * | Created By : Alok    
    */
    public function isActiveModel(Request $request)
    {
        try {
            $ulbDetails = DB::table('tbl_ulb_list')
                ->select(
                    'id',
                    'ulb_name',
                    'ulb',
                    DB::raw("CASE WHEN status = 1 THEN true ELSE false END as Is_active")
                )
                ->where('id', 11)
                ->first();

            if (!$ulbDetails) {
                return response()->json(['status' => false, 'message' => 'No record found'], 200);
            }

            return response()->json(['status' => true, 'message' => "Detail Retrieved", 'data' => $ulbDetails], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage(), 'data' => null], 500);
        }
    }
    public function demandGenerate(Request $req)
    {
        $swmBll = new SwmDemand($req);
        try {
            $shop = $swmBll->demandGenerate($req);
            DB::commit();
            // $tranId = isset($response['TranId']) ? $response['TranId'] : null;
            return response()->json(['status' => true, 'message' => "Detail Retrieved",], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage(), 'data' => null], 500);
        }
    }
}
