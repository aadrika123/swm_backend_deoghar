<?php

namespace App\Repository;

use Illuminate\Http\Request;

/**
 * | Created On-09-24-2022 
 * | Created By-
 * | Created For- Report related api 
 */
interface iReportRepository
{

    public function ReportData(Request $request);
}
