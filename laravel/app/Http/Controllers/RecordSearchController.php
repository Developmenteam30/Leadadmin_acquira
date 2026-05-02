<?php

namespace App\Http\Controllers;

use App\Helpers\CompanyScope;
use App\Models\InboundFeed;
use App\Models\OutboundFeed;
use App\Services\OutboundPushService;
use App\Services\PushIncomingDataService;
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
            $query = DB::table('feedinc')
                ->leftJoin('companies', 'feedinc.idCompany', '=', 'companies.idCompany')
                ->whereIn('feedinc.status', ['active', 'hidden'])
                ->orderBy('companies.name')
                ->orderBy('feedinc.idFeedIn')
                ->select('feedinc.idFeedIn', 'feedinc.label', 'feedinc.description', 'feedinc.idCompany', 'companies.name as companyName');

            CompanyScope::apply($query, $request->user(), 'feedinc.idCompany');

            $feeds = $query->get();

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
            // Accepted = lead was sent to at least one outgoing feed (has data_outbound)
            // Pending = not sent to any outgoing OR awaiting webhook (result='Pending' or Success with no data_outbound)
            // Rejected = result has a non-empty rejection message
            if ($status === 'accepted') {
                $query->where(function ($q) {
                    $q->whereNull('i.result')
                        ->orWhere('i.result', '')
                        ->orWhere('i.result', 'Success');
                });
                $query->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('data_outbound as o')
                        ->whereColumn('o.idRecord', 'i.idRecord')
                        ->whereColumn('o.idFeedIn', 'i.idFeedIn');
                });
            } elseif ($status === 'rejected') {
                $query->whereNotNull('i.result')
                    ->where('i.result', '!=', '')
                    ->where('i.result', '!=', 'Success')
                    ->where('i.result', '!=', 'Pending');
            } elseif ($status === 'pending') {
                $query->where(function ($q) {
                    $q->where('i.result', 'Pending')
                        ->orWhere(function ($q2) {
                            $q2->where(function ($q3) {
                                $q3->whereNull('i.result')
                                    ->orWhere('i.result', '')
                                    ->orWhere('i.result', 'Success');
                            });
                            $q2->whereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('data_outbound as o')
                                    ->whereColumn('o.idRecord', 'i.idRecord')
                                    ->whereColumn('o.idFeedIn', 'i.idFeedIn');
                            });
                        });
                });
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

            CompanyScope::apply($query, $request->user(), 'fi.idCompany');

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
            $query = DB::table('feedout')
                ->leftJoin('companies', 'feedout.idCompany', '=', 'companies.idCompany')
                ->whereIn('feedout.status', ['active', 'hidden', 'retired'])
                ->orderBy('companies.name')
                ->orderBy('feedout.idFeedOut')
                ->select('feedout.idFeedOut', 'feedout.label', 'feedout.description', 'feedout.idCompany', 'feedout.feedCategory', 'companies.name as companyName');

            CompanyScope::apply($query, $request->user(), 'feedout.idCompany');

            $feeds = $query->get();

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
            $idFeedIn = trim($request->input('idFeedIn', ''));
            $idCompany = trim($request->input('idCompany', ''));
            $email = trim($request->input('email', ''));
            $phone = preg_replace('/[^0-9]/', '', trim($request->input('phone', '')));
            $url = trim($request->input('url', ''));
            $ip = trim($request->input('ip', ''));

            if (empty($idFeedOut) && empty($idFeedIn) && empty($idCompany) && empty($email) && empty($phone) && empty($url) && empty($ip)) {
                return response()->json([
                    'status' => 0,
                    'error' => 'You must select an outgoing feed/incoming feed/company OR fill out at least one of: email, phone, URL, or IP.',
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
                    'o.buyer_response_raw',
                    'o.accepted',
                    'o.sentCount',
                    'o.cost',
                    'o.webhookCallbackId',
                    'o.url as outboundUrl',
                    'fo.label as outboundLabel',
                    'fo.description as outboundDescription',
                    'fo.responseType',
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
                    'i.cost as inboundCost',
                    'i.result as inboundResult'
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
                $query->where('o.processed', 0)->where('o.sentCount', '>', 0);
            }
            if (!empty($idFeedOut)) {
                $query->where('o.idFeedOut', $idFeedOut);
            }
            if (!empty($idFeedIn)) {
                $query->where('o.idFeedIn', $idFeedIn);
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

            CompanyScope::apply($query, $request->user(), 'fo.idCompany');

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

    public function confirmMarketplacePending(Request $request, int $idRecord, int $idFeedOut)
    {
        try {
            $row = DB::table('data_outbound as o')
                ->leftJoin('feedout as fo', 'fo.idFeedOut', '=', 'o.idFeedOut')
                ->where('o.idRecord', $idRecord)
                ->where('o.idFeedOut', $idFeedOut)
                ->select('o.*', 'fo.responseType')
                ->first();

            if (!$row) {
                return response()->json(['status' => 0, 'error' => 'Outbound record not found'], 404);
            }
            if (($row->responseType ?? 'realtime') !== 'marketplace') {
                return response()->json(['status' => 0, 'error' => 'Manual confirmation is allowed only for marketplace records'], 422);
            }
            if ((int) ($row->processed ?? 0) !== 0) {
                return response()->json(['status' => 0, 'error' => 'Record is not pending'], 422);
            }
            if (stripos((string) ($row->result ?? ''), PushIncomingDataService::MARKETPLACE_PENDING_MANUAL_REASON) === false) {
                return response()->json(['status' => 0, 'error' => 'Record is not eligible for manual confirmation'], 422);
            }

            $cost = null;
            if ($request->has('cost') && $request->input('cost') !== '' && is_numeric($request->input('cost'))) {
                $cost = (float) $request->input('cost');
            }
            $result = $request->input('result');
            $result = is_string($result) && trim($result) !== '' ? trim($result) : 'Marketplace manually confirmed';

            PushIncomingDataService::confirmMarketplacePending($idRecord, $idFeedOut, $cost, $result);

            return response()->json(['status' => 1, 'message' => 'Marketplace lead confirmed successfully']);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Failed to confirm marketplace record: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getOutboundBuyerPayload(Request $request, int $idRecord, int $idFeedOut)
    {
        try {
            $query = DB::table('data_outbound as o')
                ->join('data_inbound as i', function ($join) {
                    $join->on('i.idRecord', '=', 'o.idRecord')->on('i.idFeedIn', '=', 'o.idFeedIn');
                })
                ->join('feedout as fo', 'fo.idFeedOut', '=', 'o.idFeedOut')
                ->where('o.idRecord', $idRecord)
                ->where('o.idFeedOut', $idFeedOut)
                ->select('o.idRecord', 'o.idFeedIn', 'o.idFeedOut', 'o.webhookCallbackId', 'fo.idCompany as outboundCompanyId', 'i.*');

            CompanyScope::apply($query, $request->user(), 'fo.idCompany');

            $row = $query->first();
            if (!$row) {
                return response()->json(['status' => 0, 'error' => 'Outbound record not found'], 404);
            }

            $feedOut = OutboundFeed::find((int) $row->idFeedOut);
            if (!$feedOut) {
                return response()->json(['status' => 0, 'error' => 'Outbound feed not found'], 404);
            }
            $inboundFeed = InboundFeed::with('company')->find((int) $row->idFeedIn);

            $payload = OutboundPushService::buildRequestData(
                $row,
                $feedOut,
                $inboundFeed,
                !empty($row->webhookCallbackId) ? (string) $row->webhookCallbackId : null
            );

            return response()->json([
                'status' => 1,
                'data' => $payload,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Failed to build outbound payload: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function resendOutboundRecord(Request $request, int $idRecord, int $idFeedOut)
    {
        try {
            $row = DB::table('data_outbound as o')
                ->join('feedout as fo', 'fo.idFeedOut', '=', 'o.idFeedOut')
                ->where('o.idRecord', $idRecord)
                ->where('o.idFeedOut', $idFeedOut)
                ->select('o.idRecord', 'o.idFeedOut', 'fo.idCompany', 'o.processed', 'o.accepted')
                ->first();

            if (!$row) {
                return response()->json(['status' => 0, 'error' => 'Outbound record not found'], 404);
            }

            $scopeQuery = DB::table('feedout as fo')->where('fo.idFeedOut', $idFeedOut)->select('fo.idFeedOut');
            CompanyScope::apply($scopeQuery, $request->user(), 'fo.idCompany');
            if (!$scopeQuery->exists()) {
                return response()->json(['status' => 0, 'error' => 'Outbound record not found'], 404);
            }

            $isPending = ((int) $row->processed) === 0;
            $isRejected = ((int) $row->processed) === 1 && ((int) $row->accepted) === 0;
            if (!$isPending && !$isRejected) {
                return response()->json(['status' => 0, 'error' => 'Only pending or rejected records can be resent'], 422);
            }

            $result = PushIncomingDataService::resendSingleOutboundRecord($idRecord, $idFeedOut);

            return response()->json([
                'status' => 1,
                'message' => 'Record resent successfully.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Failed to resend record: ' . $e->getMessage(),
            ], 500);
        }
    }
}
