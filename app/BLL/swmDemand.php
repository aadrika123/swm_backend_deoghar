<?php

namespace App\BLL;

use App\Models\Demand;
use App\Traits\Api\Helpers;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;

class swmDemand
{
    use Helpers;
    protected $dbConn;
    protected $mDemand;

    public function __construct(Request $request)
    {
        $this->dbConn = $this->GetSchema($request->bearerToken());
        $this->mDemand = new Demand($this->dbConn);
    }

    /**
     * | Generate next month's demands if the previous month has a demand
     */
    public function demandGenerate()
    {
        try {
            $currentYm = Carbon::now()->format("Y-m");
            $nextMonthStart = Carbon::now()->addMonth()->startOfMonth()->toDateString();
            $nextMonthEnd = Carbon::now()->addMonth()->endOfMonth()->toDateString();

            // SQL to fetch consumers who have paid for the current month but not the next month
            $sql = "WIT0H demand_generated AS (
                           -- Find consumers who had a demand generated in the current month
                             SELECT DISTINCT consumer_id ,total_tax
                             FROM swm_demands
                             WHERE is_deactivate = 0 
                             AND TO_CHAR(payment_from, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM')
                         )
                         SELECT dg.consumer_id , dg.total_tax  
                         FROM demand_generated dg
                         LEFT JOIN swm_demands sd 
                             ON dg.consumer_id = sd.consumer_id 
                             AND TO_CHAR(sd.payment_from, 'YYYY-MM') = TO_CHAR(CURRENT_DATE + INTERVAL '1 month', 'YYYY-MM')  -- Check if next month demand exists
                         WHERE sd.consumer_id IS NULL;";

            $data = DB::connection($this->dbConn)->select($sql);
            $size = count($data);
            $demandData = [];

            foreach ($data as $key => $val) {
                DB::beginTransaction();
                echo "========= Processing ( $key [remaining: " . ($size - $key) . "]  Consumer ID: " . $val->consumer_id . ")===========\n\n";

                $row = [
                    "consumerId" => $val->consumer_id,
                    "status" => "Pending",
                    "errors" => null,
                    "response" => null
                ];

                try {
                    // Insert new demand for the next month
                    DB::connection($this->dbConn)->insert(
                        "INSERT INTO swm_demands (consumer_id, total_tax, payment_from, payment_to, paid_status, last_payment_id, user_id, stampdate, demand_date, is_deactivate) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), CURRENT_DATE, ?)",
                        [
                            $val->consumer_id,
                            $val->total_tax,
                            $nextMonthStart,
                            $nextMonthEnd,
                            0,  // Unpaid
                            0,  // No last payment
                            1,  // Default user ID
                            0   // Not deactivated
                        ]
                    );

                    $row["status"] = "Success";
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $row["status"] = "Failed";
                    $row["errors"] = $e->getMessage();
                }

                echo ("=======" . $row["status"] . "=======\n\n");
                $demandData[] = $row;
            }

            echo "========= Process Completed ===========\n";
            print_r($demandData);
        } catch (Exception $e) {
            dd("Fatal Error", $e->getMessage(), $e->getFile(), $e->getLine());
        }
    }
}
