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
        $startDate = $request->input('start', $request->input('date', date('Y-m-d', strtotime('-1 day'))));
        $endDate = $request->input('end', $request->input('date', date('Y-m-d', strtotime('-1 day'))));

        try {
            // Purchase Count & Lead Expense from stats_inbound (same source as Incoming Feeds page)
            // RT Sale Count, MP Sale Count, Lead Sales from stats_correlated (when leads are sold to outgoing feeds)
            $inboundSql = "SELECT 
                        COALESCE(ci.name, 'Null') as company_name,
                        ci.idCompany,
                        SUM(si.accepted) as purchase_count,
                        SUM(si.accepted * COALESCE(fi.costPerLead, 0)) as lead_expense
                    FROM stats_inbound AS si
                    JOIN feedinc AS fi ON fi.idFeedIn = si.idFeedIn
                    LEFT JOIN companies ci ON ci.idCompany = fi.idCompany
                    WHERE si.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
                    AND fi.feedCategory = 'phone'
                    GROUP BY ci.idCompany, ci.name";

            $correlatedSql = "SELECT 
                        ci.idCompany,
                        SUM(CASE WHEN sc.accepted > 0 AND sc.billable > 0 THEN sc.accepted ELSE 0 END) as mp_sale_count,
                        SUM(CASE WHEN sc.accepted > 0 AND sc.billable = 0 THEN sc.accepted ELSE 0 END) as rt_sale_count,
                        SUM(sc.revenuePerLead * sc.billable) as lead_sales
                    FROM stats_correlated AS sc
                    JOIN feedinc AS fi ON fi.idFeedIn = sc.idFeedIn
                    LEFT JOIN companies ci ON ci.idCompany = fi.idCompany
                    WHERE sc.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
                    AND fi.feedCategory = 'phone'
                    GROUP BY ci.idCompany";

            $inbound = collect(DB::select($inboundSql, [$startDate, $endDate]))->keyBy('idCompany');
            try {
                $correlated = collect(DB::select($correlatedSql, [$startDate, $endDate]))->keyBy('idCompany');
            } catch (\Exception $e) {
                $correlated = collect(); // stats_correlated may be empty or unavailable
            }

            // Merge: use inbound as base (has the accepted leads), add sale metrics from correlated
            $data = $inbound->map(function ($row) use ($correlated) {
                $corr = $correlated->get($row->idCompany);
                return [
                    'company_name' => $row->company_name,
                    'idCompany' => $row->idCompany,
                    'purchase_count' => (float) ($row->purchase_count ?? 0),
                    'lead_expense' => (float) ($row->lead_expense ?? 0),
                    'rt_sale_count' => (float) ($corr?->rt_sale_count ?? 0),
                    'mp_sale_count' => (float) ($corr?->mp_sale_count ?? 0),
                    'lead_sales' => (float) ($corr?->lead_sales ?? 0),
                ];
            })->values()->all();

            return response()->json([
                'status' => 1,
                'data' => $data,
                'start' => $startDate,
                'end' => $endDate,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch dashboard data: ' . $e->getMessage(),
                'data' => [],
                'start' => $startDate,
                'end' => $endDate,
            ], 500);
        }
    }
}
