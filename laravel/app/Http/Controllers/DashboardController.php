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

            // Feed-level data for expand/collapse (per incoming feed)
            $inboundFeedSql = "SELECT 
                        fi.idFeedIn,
                        fi.idCompany,
                        COALESCE(fi.label, CONCAT('Feed ', fi.idFeedIn)) as feed_name,
                        SUM(si.accepted) as purchase_count,
                        SUM(si.accepted * COALESCE(fi.costPerLead, 0)) as lead_expense
                    FROM stats_inbound AS si
                    JOIN feedinc AS fi ON fi.idFeedIn = si.idFeedIn
                    LEFT JOIN companies ci ON ci.idCompany = fi.idCompany
                    WHERE si.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
                    AND fi.feedCategory = 'phone'
                    GROUP BY fi.idFeedIn, fi.label, fi.idCompany";

            $correlatedFeedSql = "SELECT 
                        fi.idFeedIn,
                        SUM(CASE WHEN sc.accepted > 0 AND sc.billable > 0 THEN sc.accepted ELSE 0 END) as mp_sale_count,
                        SUM(CASE WHEN sc.accepted > 0 AND sc.billable = 0 THEN sc.accepted ELSE 0 END) as rt_sale_count,
                        SUM(sc.revenuePerLead * sc.billable) as lead_sales
                    FROM stats_correlated AS sc
                    JOIN feedinc AS fi ON fi.idFeedIn = sc.idFeedIn
                    WHERE sc.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
                    AND fi.feedCategory = 'phone'
                    GROUP BY fi.idFeedIn";

            $salespersonFeedSql = "SELECT 
                        fi.idFeedIn,
                        COALESCE(fi.salesperson, ci.salesperson) as idSalesperson,
                        SUM(si.accepted) as purchase_count
                    FROM stats_inbound AS si
                    JOIN feedinc AS fi ON fi.idFeedIn = si.idFeedIn
                    LEFT JOIN companies ci ON ci.idCompany = fi.idCompany
                    WHERE si.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
                    AND fi.feedCategory = 'phone'
                    GROUP BY fi.idFeedIn, COALESCE(fi.salesperson, ci.salesperson)";

            $inbound = collect(DB::select($inboundSql, [$startDate, $endDate]))->keyBy('idCompany');
            try {
                $correlated = collect(DB::select($correlatedSql, [$startDate, $endDate]))->keyBy('idCompany');
            } catch (\Exception $e) {
                $correlated = collect();
            }

            // Fetch feed-level data
            $inboundFeeds = collect(DB::select($inboundFeedSql, [$startDate, $endDate]))->keyBy('idFeedIn');
            $correlatedFeeds = collect();
            try {
                $correlatedFeeds = collect(DB::select($correlatedFeedSql, [$startDate, $endDate]))->keyBy('idFeedIn');
            } catch (\Exception $e) {
                // ignore
            }
            $salespersonFeedRows = DB::select($salespersonFeedSql, [$startDate, $endDate]);
            $purchaseBySalespersonByFeed = [];
            foreach ($salespersonFeedRows as $r) {
                $idFeedIn = $r->idFeedIn;
                $idSp = $r->idSalesperson ?? 0;
                if ($idSp === 0 || $idSp === null) {
                    $idSp = 0;
                }
                if (!isset($purchaseBySalespersonByFeed[$idFeedIn])) {
                    $purchaseBySalespersonByFeed[$idFeedIn] = [];
                }
                $purchaseBySalespersonByFeed[$idFeedIn][$idSp] = (float) ($r->purchase_count ?? 0);
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

            // Build feeds by company (for expand/collapse)
            $feedsByCompany = [];
            foreach ($inboundFeeds as $feedRow) {
                $idCompany = $feedRow->idCompany ?? 0;
                if (!isset($feedsByCompany[$idCompany])) {
                    $feedsByCompany[$idCompany] = [];
                }
                $corrFeed = $correlatedFeeds->get($feedRow->idFeedIn);
                $mpSaleCount = (float) ($corrFeed?->mp_sale_count ?? 0);
                $leadSales = (float) ($corrFeed?->lead_sales ?? 0);
                $avgScoreMpSales = $mpSaleCount > 0 ? $leadSales / $mpSaleCount : 0;

                $bySp = $purchaseBySalespersonByFeed[$feedRow->idFeedIn] ?? [];
                $purchaseBySp = [];
                foreach ($salespersons as $sp) {
                    $purchaseBySp[$sp['idUser']] = $bySp[$sp['idUser']] ?? 0;
                }

                $purchaseCount = (float) ($feedRow->purchase_count ?? 0);
                $leadExpense = (float) ($feedRow->lead_expense ?? 0);
                $avgSale = $purchaseCount > 0 ? $leadSales / $purchaseCount : 0;
                $profit = $leadSales - $leadExpense;
                $profitPercent = $leadExpense > 0 ? ($profit / $leadExpense) * 100 : 0;

                $feedsByCompany[$idCompany][] = [
                    'idFeedIn' => $feedRow->idFeedIn,
                    'feed_name' => $feedRow->feed_name ?? 'Unknown',
                    'purchase_count' => $purchaseCount,
                    'lead_expense' => $leadExpense,
                    'rt_sale_count' => (float) ($corrFeed?->rt_sale_count ?? 0),
                    'mp_sale_count' => $mpSaleCount,
                    'lead_sales' => $leadSales,
                    'avg_sale' => $avgSale,
                    'profit' => $profit,
                    'profit_percent' => $profitPercent,
                    'avg_score_mp_sales' => $avgScoreMpSales,
                    'purchase_by_salesperson' => $purchaseBySp,
                ];
            }

            // Merge: use inbound as base, add sale metrics and purchase by salesperson
            $data = $inbound->map(function ($row) use ($correlated, $purchaseBySalesperson, $salespersons, $feedsByCompany) {
                $corr = $correlated->get($row->idCompany);
                $mpSaleCount = (float) ($corr?->mp_sale_count ?? 0);
                $leadSales = (float) ($corr?->lead_sales ?? 0);
                $avgScoreMpSales = $mpSaleCount > 0 ? $leadSales / $mpSaleCount : 0;

                $bySp = $purchaseBySalesperson[$row->idCompany] ?? [];
                $purchaseBySp = [];
                foreach ($salespersons as $sp) {
                    $purchaseBySp[$sp['idUser']] = $bySp[$sp['idUser']] ?? 0;
                }

                $feeds = $feedsByCompany[$row->idCompany] ?? [];

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
                    'feeds' => $feeds,
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
