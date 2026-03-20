<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecordSearchController extends Controller
{
    /**
     * Get inbound feeds for record search dropdown (grouped by company)
     */
    public function getFeeds(Request $request)
    {
        try {
            $feeds = DB::table('feedinc')
                ->leftJoin('companies', 'feedinc.idCompany', '=', 'companies.idCompany')
                ->whereIn('feedinc.status', ['active', 'hidden'])
                ->orderBy('companies.name')
                ->orderBy('feedinc.idFeedIn')
                ->select('feedinc.idFeedIn', 'feedinc.label', 'feedinc.description', 'feedinc.idCompany', 'companies.name as companyName')
                ->get();

            $grouped = [];
            foreach ($feeds as $feed) {
                $companyName = $feed->companyName ?? 'Unknown';
                if (!isset($grouped[$companyName])) {
                    $grouped[$companyName] = [];
                }
                $grouped[$companyName][] = [
                    'idFeedIn' => $feed->idFeedIn,
                    'label' => "({$feed->idFeedIn}) {$feed->label}" . ($feed->description ? " [{$feed->description}]" : ''),
                ];
            }

            return response()->json([
                'status' => 1,
                'data' => $grouped,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Search incoming feed records (data_inbound)
     */
    public function search(Request $request)
    {
        try {
            $startDate = trim($request->input('startDate', ''));
            $endDate = trim($request->input('endDate', ''));
            $status = trim($request->input('status', 'all'));
            $idFeedIn = trim($request->input('idFeedIn', ''));
            $idCompany = trim($request->input('idCompany', ''));
            $email = trim($request->input('email', ''));
            $phone = preg_replace('/[^0-9]/', '', trim($request->input('phone', '')));
            $url = trim($request->input('url', ''));
            $ip = trim($request->input('ip', ''));
            $viewType = trim($request->input('viewType', 'condensed'));

            if (empty($idFeedIn) && empty($idCompany) && empty($email) && empty($phone) && empty($url) && empty($ip)) {
                return response()->json([
                    'status' => 0,
                    'error' => 'You must select a feed/company OR fill out at least one of: email, phone, URL, or IP.',
                    'data' => [],
                ], 400);
            }

            $query = DB::table('data_inbound as i')
                ->leftJoin('feedinc as fi', 'fi.idFeedIn', '=', 'i.idFeedIn')
                ->leftJoin('companies as ci', 'fi.idCompany', '=', 'ci.idCompany')
                ->select(
                    'i.idRecord',
                    'i.idFeedIn',
                    'i.timestamp',
                    DB::raw("DATE_FORMAT(i.timestamp, '%Y-%m-%d %H:%i:%s') as timestampConverted"),
                    'i.leadstamp',
                    'i.listcode',
                    'i.url',
                    'i.fname',
                    'i.lname',
                    'i.addr',
                    'i.addr2',
                    'i.city',
                    'i.state',
                    'i.zip',
                    'i.country',
                    'i.dob',
                    'i.gender',
                    'i.landline',
                    'i.cellphone',
                    'i.email',
                    'i.ip',
                    'i.cost',
                    'i.result',
                    'i.rawData',
                    'i.custom1',
                    'i.custom2',
                    'i.custom3',
                    'i.custom4',
                    'i.custom5',
                    'i.custom6',
                    'fi.label',
                    'fi.description',
                    'ci.name as companyName'
                )
                ->orderByDesc('i.timestamp')
                ->limit(500);

            if (!empty($startDate)) {
                $query->where('i.timestamp', '>=', $startDate . ' 00:00:00');
            }
            if (!empty($endDate)) {
                $query->where('i.timestamp', '<=', $endDate . ' 23:59:59');
            }
            // Accepted = result is NULL, '', or 'Success' (LiveFeed stores accepted leads with result='Success')
            // Rejected = result has a non-empty rejection message (excludes Pending)
            // Pending = result is 'Pending' (awaiting buyer response)
            if ($status === 'accepted') {
                $query->where(function ($q) {
                    $q->whereNull('i.result')
                        ->orWhere('i.result', '')
                        ->orWhere('i.result', 'Success');
                });
            } elseif ($status === 'rejected') {
                $query->whereNotNull('i.result')
                    ->where('i.result', '!=', '')
                    ->where('i.result', '!=', 'Success')
                    ->where('i.result', '!=', 'Pending');
            } elseif ($status === 'pending') {
                $query->where('i.result', 'Pending');
            }
            if (!empty($idFeedIn)) {
                $query->where('i.idFeedIn', $idFeedIn);
            }
            if (!empty($idCompany)) {
                $query->where('fi.idCompany', $idCompany);
            }
            if (!empty($email)) {
                $query->where('i.email', $email);
            }
            if (!empty($url)) {
                $query->where('i.url', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $url) . '%');
            }
            if (!empty($ip)) {
                $query->where('i.ip', $ip);
            }
            if (!empty($phone)) {
                $query->where(function ($q) use ($phone) {
                    $q->where('i.cellphone', $phone)->orWhere('i.landline', $phone);
                });
            }

            $records = $query->get();

            if ($viewType === 'expanded' && $records->isNotEmpty()) {
                $idRecords = $records->pluck('idRecord')->unique()->values()->all();
                $outboundByRecord = DB::table('data_outbound as o')
                    ->leftJoin('feedout as fo', 'fo.idFeedOut', '=', 'o.idFeedOut')
                    ->leftJoin('companies as co', 'fo.idCompany', '=', 'co.idCompany')
                    ->whereIn('o.idRecord', $idRecords)
                    ->select(
                        'o.idRecord',
                        'o.idFeedOut',
                        'o.timestamp',
                        DB::raw("DATE_FORMAT(o.timestamp, '%Y-%m-%d %H:%i:%s') as timestampConverted"),
                        'o.result',
                        'fo.label',
                        'co.name as companyName'
                    )
                    ->orderBy('o.idRecord')
                    ->orderByDesc('o.timestamp')
                    ->get()
                    ->groupBy('idRecord');
            } else {
                $outboundByRecord = collect();
            }

            $recordsData = $records->map(function ($r) use ($outboundByRecord, $viewType) {
                $item = (array) $r;
                if ($viewType === 'expanded') {
                    $item['outboundRecords'] = $outboundByRecord->get($r->idRecord, collect())->values()->all();
                }
                return $item;
            });

            return response()->json([
                'status' => 1,
                'data' => $recordsData,
                'count' => $records->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Search failed: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get outgoing feeds for record search dropdown (grouped by company)
     */
    public function getOutboundFeeds(Request $request)
    {
        try {
            $feeds = DB::table('feedout')
                ->leftJoin('companies', 'feedout.idCompany', '=', 'companies.idCompany')
                ->whereIn('feedout.status', ['active', 'hidden', 'retired'])
                ->orderBy('companies.name')
                ->orderBy('feedout.idFeedOut')
                ->select('feedout.idFeedOut', 'feedout.label', 'feedout.description', 'feedout.idCompany', 'feedout.feedCategory', 'companies.name as companyName')
                ->get();

            $grouped = [];
            foreach ($feeds as $feed) {
                $companyName = $feed->companyName ?? 'Unknown';
                if (!isset($grouped[$companyName])) {
                    $grouped[$companyName] = [];
                }
                $grouped[$companyName][] = [
                    'idFeedOut' => $feed->idFeedOut,
                    'label' => "({$feed->idFeedOut}) {$feed->label}" . ($feed->description ? " [{$feed->description}]" : ''),
                ];
            }

            return response()->json([
                'status' => 1,
                'data' => $grouped,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Search outgoing feed records (data_outbound)
     */
    public function searchOutbound(Request $request)
    {
        try {
            $startDate = trim($request->input('startDate', ''));
            $endDate = trim($request->input('endDate', ''));
            $status = trim($request->input('status', 'all'));
            $idFeedOut = trim($request->input('idFeedOut', ''));
            $idCompany = trim($request->input('idCompany', ''));
            $email = trim($request->input('email', ''));
            $phone = preg_replace('/[^0-9]/', '', trim($request->input('phone', '')));
            $url = trim($request->input('url', ''));
            $ip = trim($request->input('ip', ''));

            if (empty($idFeedOut) && empty($idCompany) && empty($email) && empty($phone) && empty($url) && empty($ip)) {
                return response()->json([
                    'status' => 0,
                    'error' => 'You must select an outgoing feed/company OR fill out at least one of: email, phone, URL, or IP.',
                    'data' => [],
                ], 400);
            }

            $query = DB::table('data_outbound as o')
                ->leftJoin('data_inbound as i', function ($join) {
                    $join->on('i.idRecord', '=', 'o.idRecord')->on('i.idFeedIn', '=', 'o.idFeedIn');
                })
                ->leftJoin('feedout as fo', 'fo.idFeedOut', '=', 'o.idFeedOut')
                ->leftJoin('feedinc as fi', 'fi.idFeedIn', '=', 'o.idFeedIn')
                ->leftJoin('companies as co', 'fo.idCompany', '=', 'co.idCompany')
                ->leftJoin('companies as ci', 'fi.idCompany', '=', 'ci.idCompany')
                ->select(
                    'o.idRecord',
                    'o.idFeedIn',
                    'o.idFeedOut',
                    'o.processed',
                    'o.timestamp',
                    DB::raw("DATE_FORMAT(o.timestamp, '%Y-%m-%d %H:%i:%s') as timestampConverted"),
                    'o.result',
                    'o.accepted',
                    'o.cost',
                    'o.url as outboundUrl',
                    'fo.label as outboundLabel',
                    'fo.description as outboundDescription',
                    'co.name as outboundCompanyName',
                    'fi.label as inboundLabel',
                    'ci.name as inboundCompanyName',
                    'i.email',
                    'i.fname',
                    'i.lname',
                    'i.addr',
                    'i.addr2',
                    'i.city',
                    'i.state',
                    'i.zip',
                    'i.country',
                    'i.dob',
                    'i.gender',
                    'i.landline',
                    'i.cellphone',
                    'i.ip',
                    'i.leadstamp',
                    'i.rawData',
                    'i.cost as inboundCost'
                )
                ->orderByRaw('COALESCE(o.timestamp, i.timestamp) DESC')
                ->limit(500);

            // Use inbound timestamp for pending records (o.timestamp may be null)
            $tsCol = "COALESCE(o.timestamp, i.timestamp)";
            if (!empty($startDate)) {
                $query->whereRaw("{$tsCol} >= ?", [$startDate . ' 00:00:00']);
            }
            if (!empty($endDate)) {
                $query->whereRaw("{$tsCol} <= ?", [$endDate . ' 23:59:59']);
            }
            if ($status === 'accepted') {
                $query->where('o.accepted', 1)->where('o.processed', 1);
            } elseif ($status === 'rejected') {
                $query->where('o.accepted', 0)->where('o.processed', 1);
            } elseif ($status === 'pending') {
                $query->where('o.processed', 0);
            }
            if (!empty($idFeedOut)) {
                $query->where('o.idFeedOut', $idFeedOut);
            }
            if (!empty($idCompany)) {
                $query->where('fo.idCompany', $idCompany);
            }
            if (!empty($email)) {
                $query->where('i.email', $email);
            }
            if (!empty($url)) {
                $query->where(function ($q) use ($url) {
                    $q->where('o.url', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $url) . '%')
                        ->orWhere('i.url', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $url) . '%');
                });
            }
            if (!empty($ip)) {
                $query->where('i.ip', $ip);
            }
            if (!empty($phone)) {
                $query->where(function ($q) use ($phone) {
                    $q->where('i.cellphone', $phone)->orWhere('i.landline', $phone);
                });
            }

            $records = $query->get();

            return response()->json([
                'status' => 1,
                'data' => $records->map(fn ($r) => (array) $r)->values()->all(),
                'count' => $records->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Search failed: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
