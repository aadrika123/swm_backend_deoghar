<?

namespace App\Repository;

use App\Models\Apartment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * | Created On-08-09-2022 
 * | Created By-
 * | Created For- Consumer related api 
 */
class ApartmentRepository
{
    public function ApartmentList(Request $request)
    {

        try
        {   
            print_r("hello'");
            $conArr = array();
            if(isset($request->apartmentId))
            {
                $field = 'id';
                $operator = '=';
                $value = $request->apartmentId;
            }

            if(isset($request->apartmentName))
            {
                $field = 'name';
                $operator = 'like';
                $value = '%'.$request->apartmentName.'%';
            }
            
            $apartmentList = Apartment::where('is_blocks', 0)
                                ->where($field, $operator, $value)
                                ->get();
            
            foreach($apartmentList as $apartment)
            {

                $demand = DB::table('tbl_demand')
                            ->select(DB::raw('consumer_id,sum(total_tax) as total_tax,paid_status,deactivate_status,max(demand_date) as demand_upto'))
                            ->join('tbl_consumer', 'tbl_demand.id', '=', 'tbl_demand.consumer_id')
                            ->where('tbl_consumer.apt_mstr_id', $apartment->id)
                            ->where('paid_status', 0)
                            ->where('deactivate_status', 0)
                            ->groupByRaw('consumer_id,paid_status,deactivate_status')
                            ->first();
                
                $con['id'] = $apartment->id;
                $con['wardNo'] = $apartment->ward_no;
                $con['apartmentName'] = $apartment->apt_name;
                $con['apartmentCode'] = $apartment->apt_code;
                $con['address'] = $apartment->apt_address;
                $con['mobileNo'] = $apartment->mobile_no;
                $con['totalDemand'] = ($demand)?$demand->total_tax:'0.00';
                $con['demandUpto'] = ($demand)?$demand->demand_upto:'';
                $con['paidStatus'] = ($demand)?$demand->paid_status:'';


                $conArr[] = $con;

            }
            return response($conArr, 200);
        } 
        catch (Exception $e) 
        {
            return $e;
        }
        
    }
}