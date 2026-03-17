<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get Life Leads dashboard data
     */
    public function getLifeLeads(Request $request)
    {
        $date = $request->input('date', date('Y-m-d', strtotime('-1 day')));

        try {
            $sql = "SELECT 
                        COALESCE(ci.name, 'Null') as company_name,
                        ci.idCompany,
                        SUM(sc.accepted) as purchase_count,
                        SUM(sc.costPerLead * sc.billable) as lead_expense,
                        SUM(CASE WHEN sc.accepted > 0 AND sc.billable > 0 THEN sc.accepted ELSE 0 END) as mp_sale_count,
                        SUM(CASE WHEN sc.accepted > 0 AND sc.billable = 0 THEN sc.accepted ELSE 0 END) as rt_sale_count,
                        SUM(sc.revenuePerLead * sc.billable) as lead_sales
                    FROM stats_correlated AS sc
                    JOIN feedinc AS fi ON fi.idFeedIn = sc.idFeedIn
                    LEFT JOIN companies ci ON ci.idCompany = fi.idCompany
                    WHERE sc.stamp = ?
                    AND fi.feedCategory = 'phone'
                    GROUP BY ci.idCompany, COALESCE(ci.name, 'Null')
                    ORDER BY COALESCE(ci.name, 'Null')";

            $results = DB::select($sql, [$date]);

            // Convert to array format for JSON response
            $data = array_map(function ($row) {
                return (array) $row;
            }, $results);

            return response()->json([
                'status' => 1,
                'data' => $data,
                'date' => $date,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch dashboard data: ' . $e->getMessage(),
                'data' => [],
                'date' => $date,
            ], 500);
        }
    }
}
