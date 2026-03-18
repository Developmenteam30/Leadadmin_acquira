<?php

namespace App\Http\Controllers;

use App\Models\User;
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

            // Purchase count by salesperson per company (feedinc.salesperson overrides companies.salesperson)
            $salespersonSql = "SELECT 
                        ci.idCompany,
                        COALESCE(fi.salesperson, ci.salesperson) as idSalesperson,
                        SUM(si.accepted) as purchase_count
                    FROM stats_inbound AS si
                    JOIN feedinc AS fi ON fi.idFeedIn = si.idFeedIn
                    LEFT JOIN companies ci ON ci.idCompany = fi.idCompany
                    WHERE si.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
                    AND fi.feedCategory = 'phone'
                    GROUP BY ci.idCompany, ci.name, COALESCE(fi.salesperson, ci.salesperson)";

            $inbound = collect(DB::select($inboundSql, [$startDate, $endDate]))->keyBy('idCompany');
            try {
                $correlated = collect(DB::select($correlatedSql, [$startDate, $endDate]))->keyBy('idCompany');
            } catch (\Exception $e) {
                $correlated = collect();
            }

            $salespersonRows = DB::select($salespersonSql, [$startDate, $endDate]);
            $purchaseBySalesperson = [];
            $hasUnassigned = false;
            foreach ($salespersonRows as $r) {
                $idCompany = $r->idCompany;
                $idSp = $r->idSalesperson ?? 0;
                if ($idSp === 0 || $idSp === null) {
                    $idSp = 0;
                    $hasUnassigned = true;
                }
                if (!isset($purchaseBySalesperson[$idCompany])) {
                    $purchaseBySalesperson[$idCompany] = [];
                }
                $purchaseBySalesperson[$idCompany][$idSp] = (float) ($r->purchase_count ?? 0);
            }

            $salespersons = User::whereRaw('(accessBits & ?) > 0', [0x200])
                ->orderBy('username')
                ->get(['idUser', 'fullName'])
                ->map(fn ($u) => ['idUser' => $u->idUser, 'fullName' => $u->fullName ?? ''])
                ->values()
                ->all();
            if ($hasUnassigned) {
                array_unshift($salespersons, ['idUser' => 0, 'fullName' => 'Unassigned']);
            }

            // Merge: use inbound as base, add sale metrics and purchase by salesperson
            $data = $inbound->map(function ($row) use ($correlated, $purchaseBySalesperson, $salespersons) {
                $corr = $correlated->get($row->idCompany);
                $mpSaleCount = (float) ($corr?->mp_sale_count ?? 0);
                $leadSales = (float) ($corr?->lead_sales ?? 0);
                $avgScoreMpSales = $mpSaleCount > 0 ? $leadSales / $mpSaleCount : 0;

                $bySp = $purchaseBySalesperson[$row->idCompany] ?? [];
                $purchaseBySp = [];
                foreach ($salespersons as $sp) {
                    $purchaseBySp[$sp['idUser']] = $bySp[$sp['idUser']] ?? 0;
                }

                return [
                    'company_name' => $row->company_name,
                    'idCompany' => $row->idCompany,
                    'purchase_count' => (float) ($row->purchase_count ?? 0),
                    'lead_expense' => (float) ($row->lead_expense ?? 0),
                    'rt_sale_count' => (float) ($corr?->rt_sale_count ?? 0),
                    'mp_sale_count' => $mpSaleCount,
                    'lead_sales' => $leadSales,
                    'avg_score_mp_sales' => $avgScoreMpSales,
                    'purchase_by_salesperson' => $purchaseBySp,
                ];
            })->values()->all();

            return response()->json([
                'status' => 1,
                'data' => $data,
                'salespersons' => $salespersons,
                'start' => $startDate,
                'end' => $endDate,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch dashboard data: ' . $e->getMessage(),
                'data' => [],
                'salespersons' => [],
                'start' => $startDate,
                'end' => $endDate,
            ], 500);
        }
    }
}
