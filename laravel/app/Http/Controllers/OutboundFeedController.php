<?php

namespace App\Http\Controllers;

use App\Models\OutboundFeed;
use App\Models\Company;
use App\Helpers\CompanyScope;
use App\Jobs\ResendPendingMarketplaceJob;
use App\Jobs\RetryOutboundRejectionsJob;
use App\Services\OutboundTestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class OutboundFeedController extends Controller
{
    /**
     * Get outbound feeds grouped by company
     */
    public function index(Request $request)
    {
        try {
            $status = $request->input('status');
            $feedCategory = $request->input('feedCategory');
            $statsStart = $request->input('statsStart', date('Y-m-d', strtotime('-30 days')));
            $statsEnd = $request->input('statsEnd', date('Y-m-d'));

            $query = DB::table('feedout')
                ->leftJoin('companies', 'feedout.idCompany', '=', 'companies.idCompany')
                ->select('feedout.*', 'companies.name as companyName');

            if ($status) {
                $query->where('feedout.status', $status);
            }

            if ($feedCategory !== null && $feedCategory !== '') {
                $query->where('feedout.feedCategory', $feedCategory);
            }

            CompanyScope::apply($query, $request->user(), 'feedout.idCompany');

            return $this->getFeedsGroupedByCompany($query, $statsStart, $statsEnd);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch outgoing feeds: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get outgoing ping feeds grouped by company
     */
    public function ping(Request $request)
    {
        try {
            $status = $request->input('status');
            $statsStart = $request->input('statsStart', date('Y-m-d', strtotime('-30 days')));
            $statsEnd = $request->input('statsEnd', date('Y-m-d'));

            $query = DB::table('feedout')
                ->leftJoin('companies', 'feedout.idCompany', '=', 'companies.idCompany')
                ->select('feedout.*', 'companies.name as companyName')
                ->where('feedout.feedCategory', 'phone-preping'); // Only ping feeds

            if ($status) {
                $query->where('feedout.status', $status);
            }

            CompanyScope::apply($query, $request->user(), 'feedout.idCompany');

            return $this->getFeedsGroupedByCompany($query, $statsStart, $statsEnd);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch outgoing ping feeds: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Helper method to group feeds by company and calculate stats
     */
    private function getFeedsGroupedByCompany($query, $statsStart, $statsEnd)
    {
        try {
            $feeds = $query->orderBy('companies.name')
                ->get();

            $feedIds = $feeds->pluck('idFeedOut')->filter()->values();
            $incomingFeedsByOutbound = [];
            if ($feedIds->isNotEmpty()) {
                $populationRows = DB::table('feedPopulation as fp')
                    ->leftJoin('feedinc as fi', 'fi.idFeedIn', '=', 'fp.idFeedIn')
                    ->leftJoin('companies as c', 'c.idCompany', '=', 'fi.idCompany')
                    ->whereIn('fp.idFeedOut', $feedIds)
                    ->select(
                        'fp.idFeedOut',
                        'fp.idFeedIn',
                        'fi.label as inboundLabel',
                        'c.name as inboundCompanyName'
                    )
                    ->orderBy('c.name')
                    ->orderBy('fi.label')
                    ->get();

                $incomingStatsRows = DB::table('data_outbound as o')
                    ->leftJoin('data_inbound as i', function ($join) {
                        $join->on('i.idRecord', '=', 'o.idRecord')->on('i.idFeedIn', '=', 'o.idFeedIn');
                    })
                    ->whereIn('o.idFeedOut', $feedIds)
                    ->whereRaw('COALESCE(o.timestamp, i.timestamp) >= ?', [$statsStart . ' 00:00:00'])
                    ->whereRaw('COALESCE(o.timestamp, i.timestamp) <= ?', [$statsEnd . ' 23:59:59'])
                    ->groupBy('o.idFeedOut', 'o.idFeedIn')
                    ->selectRaw(
                        'o.idFeedOut, o.idFeedIn,
                        SUM(CASE WHEN o.processed = 1 AND o.accepted = 1 THEN 1 ELSE 0 END) as accepted,
                        SUM(CASE WHEN o.processed = 1 AND o.accepted = 0 THEN 1 ELSE 0 END) as rejected,
                        SUM(CASE WHEN o.processed = 0 AND COALESCE(o.sentCount, 0) > 0 THEN 1 ELSE 0 END) as pending'
                    )
                    ->get();

                $incomingStatsByFeed = [];
                $queuedByOutbound = [];
                foreach ($incomingStatsRows as $statsRow) {
                    $outboundId = (int) ($statsRow->idFeedOut ?? 0);
                    $pendingCount = (int) ($statsRow->pending ?? 0);
                    $incomingStatsByFeed[(int) ($statsRow->idFeedOut ?? 0) . ':' . (int) ($statsRow->idFeedIn ?? 0)] = [
                        'accepted' => (int) ($statsRow->accepted ?? 0),
                        'rejected' => (int) ($statsRow->rejected ?? 0),
                        'pending' => $pendingCount,
                    ];
                    $queuedByOutbound[$outboundId] = ($queuedByOutbound[$outboundId] ?? 0) + $pendingCount;
                }

                foreach ($populationRows as $row) {
                    $idFeedOut = (int) ($row->idFeedOut ?? 0);
                    $idFeedIn = (int) ($row->idFeedIn ?? 0);
                    $statsKey = $idFeedOut . ':' . $idFeedIn;
                    $stats = $incomingStatsByFeed[$statsKey] ?? ['accepted' => 0, 'rejected' => 0, 'pending' => 0];
                    if (!isset($incomingFeedsByOutbound[$idFeedOut])) {
                        $incomingFeedsByOutbound[$idFeedOut] = [];
                    }
                    $incomingFeedsByOutbound[$idFeedOut][] = [
                        'idFeedIn' => $idFeedIn,
                        'label' => $row->inboundLabel ?? '',
                        'companyName' => $row->inboundCompanyName ?? '',
                        'accepted' => $stats['accepted'],
                        'rejected' => $stats['rejected'],
                        'pending' => $stats['pending'],
                    ];
                }
            }

            // Group by company and calculate stats
            $companyGroups = [];
            foreach ($feeds as $feed) {
                $companyId = $feed->idCompany ?? 0;
                $companyName = $feed->companyName ?? 'Unknown Company';

                if (!isset($companyGroups[$companyId])) {
                    $companyGroups[$companyId] = [
                        'idCompany' => $companyId,
                        'name' => $companyName,
                        'feeds' => [],
                        'totalFeeds' => 0,
                        'totalActive' => 0,
                        'totalAccepted' => 0,
                        'totalRejected' => 0,
                        'totalQueued' => 0,
                    ];
                }

                // Get stats for this feed
                $stats = $this->getFeedStats($feed->idFeedOut, $statsStart, $statsEnd);
                $feedQueuedCount = $queuedByOutbound[(int) ($feed->idFeedOut ?? 0)] ?? 0;
                
                $feedData = [
                    'idFeedOut' => $feed->idFeedOut,
                    'label' => $feed->label ?? '',
                    'description' => $feed->description ?? '',
                    'status' => $feed->status ?? 'active',
                    'cron' => $feed->cron ?? '0',
                    'queued' => $feedQueuedCount,
                    'accepted' => $stats['accepted'],
                    'rejected' => $stats['rejected'],
                    'queuedCount' => $feedQueuedCount,
                    'incomingFeeds' => $incomingFeedsByOutbound[(int) ($feed->idFeedOut ?? 0)] ?? [],
                ];

                $companyGroups[$companyId]['feeds'][] = $feedData;
                $companyGroups[$companyId]['totalFeeds']++;
                if ($feed->status === 'active') {
                    $companyGroups[$companyId]['totalActive']++;
                }
                $companyGroups[$companyId]['totalAccepted'] += $stats['accepted'];
                $companyGroups[$companyId]['totalRejected'] += $stats['rejected'];
                $companyGroups[$companyId]['totalQueued'] += $feedQueuedCount;
            }

            // Sort feeds inside each company by name ASC, then type/description DESC.
            foreach ($companyGroups as &$companyGroup) {
                usort($companyGroup['feeds'], function ($a, $b) {
                    $nameCompare = strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
                    if ($nameCompare !== 0) {
                        return $nameCompare;
                    }

                    $typeCompare = strcasecmp((string)($b['description'] ?? ''), (string)($a['description'] ?? ''));
                    if ($typeCompare !== 0) {
                        return $typeCompare;
                    }

                    return ($a['idFeedOut'] ?? 0) <=> ($b['idFeedOut'] ?? 0);
                });
            }
            unset($companyGroup);

            // Convert to array and sort by company name
            $result = array_values($companyGroups);
            usort($result, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            return response()->json([
                'status' => 1,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch feeds: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get stats for a specific feed
     */
    private function getFeedStats($idFeedOut, $startDate, $endDate)
    {
        try {
            $stats = DB::table('stats_outbound')
                ->where('idFeedOut', $idFeedOut)
                ->whereBetween('stamp', [$startDate, $endDate])
                ->selectRaw('SUM(accepted) as accepted, SUM(rejected) as rejected')
                ->first();

            return [
                'accepted' => (int)($stats->accepted ?? 0),
                'rejected' => (int)($stats->rejected ?? 0),
            ];
        } catch (\Exception $e) {
            return [
                'accepted' => 0,
                'rejected' => 0,
            ];
        }
    }

    /**
     * Get a single outbound feed
     */
    public function show($id)
    {
        try {
            $feed = OutboundFeed::with('company')->find($id);

            if (!$feed) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Feed not found',
                ], 404);
            }

            // Parse JSON fields if they exist
            if (!empty($feed->staticFieldsJSON)) {
                $feed->staticFields = is_array($feed->staticFieldsJSON) ? $feed->staticFieldsJSON : json_decode($feed->staticFieldsJSON, true);
                // Convert to array format for form
                $feed->staticFields = $feed->staticFields ? array_map(function($value, $key) {
                    return ['field' => $key, 'value' => $value];
                }, array_values($feed->staticFields), array_keys($feed->staticFields)) : [];
            } else {
                $feed->staticFields = [];
            }

            if (!empty($feed->varFieldsJSON)) {
                $feed->varFields = is_array($feed->varFieldsJSON) ? $feed->varFieldsJSON : json_decode($feed->varFieldsJSON, true);
                // Convert to array format for form
                $feed->varFields = $feed->varFields ? array_map(function($value, $key) {
                    return ['field' => $key, 'map' => $value];
                }, array_values($feed->varFields), array_keys($feed->varFields)) : [];
            } else {
                $feed->varFields = [];
            }

            // Parse valueMap
            if (!empty($feed->valueMap)) {
                $valueMapData = is_string($feed->valueMap) ? json_decode($feed->valueMap, true) : $feed->valueMap;
                $feed->valueMap = is_array($valueMapData) ? $valueMapData : [];
            } else {
                $feed->valueMap = [];
            }

            // Parse notifyThresholdDays
            if (!empty($feed->notifyThresholdDays)) {
                $feed->notifyThresholdDays = is_array($feed->notifyThresholdDays) 
                    ? $feed->notifyThresholdDays 
                    : explode(',', $feed->notifyThresholdDays);
                $feed->notifyThresholdDays = array_map('intval', $feed->notifyThresholdDays);
            } else {
                $feed->notifyThresholdDays = [];
            }

            // Format notifyThresholdTime if it exists
            if (!empty($feed->notifyThresholdTime)) {
                $time = is_string($feed->notifyThresholdTime) ? $feed->notifyThresholdTime : $feed->notifyThresholdTime->format('H:i:s');
                if (preg_match('/(\d{2}):(\d{2}):(\d{2})/', $time, $matches)) {
                    $hour = (int)$matches[1];
                    $minute = $matches[2];
                    $ampm = $hour >= 12 ? 'PM' : 'AM';
                    if ($hour > 12) {
                        $hour -= 12;
                    } elseif ($hour === 0) {
                        $hour = 12;
                    }
                    $feed->notifyThresholdTime = sprintf('%d:%s%s', $hour, $minute, $ampm);
                }
            }

            // Parse urlassignments to array format
            if (!empty($feed->urlassignments)) {
                $pairs = explode(';', $feed->urlassignments);
                $feed->urlassignments = [];
                foreach ($pairs as $pair) {
                    $parts = explode('=', $pair, 2);
                    $feed->urlassignments[] = ['url' => $parts[0] ?? '', 'id' => $parts[1] ?? ''];
                }
            } else {
                $feed->urlassignments = [];
            }

            // Parse processingSchedule
            if (!empty($feed->processingSchedule)) {
                $feed->processingSchedule = is_string($feed->processingSchedule) ? json_decode($feed->processingSchedule, true) : $feed->processingSchedule;
            } else {
                $feed->processingSchedule = [
                    'sun' => ['enabled' => true, 'startTime' => '', 'endTime' => ''],
                    'mon' => ['enabled' => true, 'startTime' => '', 'endTime' => ''],
                    'tue' => ['enabled' => true, 'startTime' => '', 'endTime' => ''],
                    'wed' => ['enabled' => true, 'startTime' => '', 'endTime' => ''],
                    'thu' => ['enabled' => true, 'startTime' => '', 'endTime' => ''],
                    'fri' => ['enabled' => true, 'startTime' => '', 'endTime' => ''],
                    'sat' => ['enabled' => true, 'startTime' => '', 'endTime' => ''],
                ];
            }

            // Convert ENUM fields to strings
            $feed->cron = $feed->cron === '1' || $feed->cron === 1 || $feed->cron === true ? '1' : '0';

            return response()->json([
                'status' => 1,
                'data' => $feed,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch feed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle cron/enabled status of an outbound feed
     */
    public function toggleCron(Request $request, $id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Feed not found',
                ], 404);
            }

            $cron = $request->input('cron');
            if ($cron === null || $cron === '') {
                return response()->json([
                    'status' => 0,
                    'error' => 'cron value is required (0 or 1)',
                ], 422);
            }

            $cron = filter_var($cron, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            $feed->cron = $cron;
            $feed->save();

            return response()->json([
                'status' => 1,
                'data' => ['cron' => $cron],
                'message' => $cron === '1' ? 'Cron enabled' : 'Cron disabled',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to toggle cron: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle feed status (active/inactive) - controls whether feed receives and processes leads
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Feed not found',
                ], 404);
            }

            $enabled = $request->input('enabled');
            if ($enabled === null || $enabled === '') {
                return response()->json([
                    'status' => 0,
                    'error' => 'enabled value is required (true/false)',
                ], 422);
            }

            $enabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
            $feed->status = $enabled ? 'active' : 'hidden';
            $feed->save();

            return response()->json([
                'status' => 1,
                'data' => ['status' => $feed->status],
                'message' => $enabled ? 'Feed activated' : 'Feed deactivated',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to toggle status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an outbound feed
     */
    public function update(Request $request, $id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Feed not found',
                ], 404);
            }

            $request->validate([
                'label' => 'required|string|max:30',
                'idCompany' => 'required|integer',
                'feedType' => 'required|string|in:curlPOST,curlGET,JSON,csvString,soapPOST,curlPOST-urlencoded,xmlPOST',
                'postUrl' => 'required|string|max:1000',
                'timezone' => 'required|string',
                'prepingEnabled' => 'sometimes|boolean',
                'prepingUrl' => [
                    Rule::requiredIf(fn () => $request->boolean('prepingEnabled')),
                    'nullable',
                    'string',
                    'max:1000',
                ],
                'prepingHttpMethod' => 'nullable|string|in:GET,POST',
                'prepingAuthType' => 'nullable|string|in:none,bearer,basic',
                'prepingAuthValue' => [
                    Rule::requiredIf(fn () => $request->boolean('prepingEnabled')
                        && in_array($request->input('prepingAuthType', 'none'), ['bearer', 'basic'], true)),
                    'nullable',
                    'string',
                ],
            ]);

            // Process staticFieldsJSON
            $staticFieldsJSON = null;
            if ($request->has('staticFields') && is_array($request->staticFields)) {
                $staticFields = [];
                foreach ($request->staticFields as $field) {
                    if (!empty($field['field']) && isset($field['value'])) {
                        $staticFields[trim($field['field'])] = trim($field['value']);
                    }
                }
                if (!empty($staticFields)) {
                    $staticFieldsJSON = $staticFields;
                }
            }

            // Process varFieldsJSON and fieldMap
            $varFieldsJSON = null;
            $fieldMap = null;
            if ($request->has('varFields') && is_array($request->varFields)) {
                $varFields = [];
                $fieldMapArray = [];
                foreach ($request->varFields as $field) {
                    if (!empty($field['field']) && isset($field['map'])) {
                        $varFields[trim($field['field'])] = trim($field['map']);
                        $fieldMapArray[trim($field['field'])] = trim($field['map']);
                    }
                }
                if (!empty($varFields)) {
                    $varFieldsJSON = $varFields;
                    $fieldMap = json_encode($fieldMapArray);
                }
            }

            // Process valueMap
            $valueMap = null;
            if ($request->has('valueMap') && is_array($request->valueMap)) {
                $valueMapArray = [];
                foreach ($request->valueMap as $map) {
                    if (!empty($map['field']) && isset($map['oldValue']) && isset($map['newValue'])) {
                        $valueMapArray[] = [
                            'field' => trim($map['field']),
                            'oldValue' => trim($map['oldValue']),
                            'newValue' => trim($map['newValue']),
                        ];
                    }
                }
                if (!empty($valueMapArray)) {
                    $valueMap = json_encode($valueMapArray);
                }
            }

            // Process notifyThresholdDays
            $notifyThresholdDays = null;
            if ($request->has('notifyThresholdDays') && is_array($request->notifyThresholdDays) && !empty($request->notifyThresholdDays)) {
                $notifyThresholdDays = implode(',', $request->notifyThresholdDays);
            }

            // Process urlassignments from array format
            $urlassignments = null;
            if ($request->has('urlassignments') && is_array($request->urlassignments)) {
                $pairs = [];
                foreach ($request->urlassignments as $item) {
                    if (!empty($item['url']) || !empty($item['id'])) {
                        $url = str_replace(['=', ';'], '', trim($item['url'] ?? ''));
                        $id = str_replace(['=', ';'], '', trim($item['id'] ?? ''));
                        $pairs[] = $url . '=' . $id;
                    }
                }
                $urlassignments = !empty($pairs) ? implode(';', $pairs) : null;
            } elseif ($request->urlassignments && is_string($request->urlassignments)) {
                $urlassignments = trim($request->urlassignments) ?: null;
            }

            // Process processingSchedule
            $processingSchedule = null;
            if ($request->has('processingSchedule') && is_array($request->processingSchedule)) {
                $schedule = [];
                $days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
                foreach ($days as $day) {
                    $dayData = $request->processingSchedule[$day] ?? ['enabled' => true, 'startTime' => '', 'endTime' => ''];
                    $schedule[$day] = [
                        'enabled' => !empty($dayData['enabled']),
                        'startTime' => $dayData['startTime'] ?? '',
                        'endTime' => $dayData['endTime'] ?? '',
                    ];
                }
                $processingSchedule = json_encode($schedule);
            }

            // Process notifyThresholdTime
            $notifyThresholdTime = null;
            if ($request->has('notifyThresholdTime') && !empty($request->notifyThresholdTime)) {
                $timeStr = $request->notifyThresholdTime;
                $timeStr = strtoupper($timeStr);
                $timeStr = preg_replace('/\s+/', '', $timeStr);
                if (preg_match('/(\d{1,2}):?(\d{2})?\s*(AM|PM)/', $timeStr, $matches)) {
                    $hour = (int)$matches[1];
                    $minute = isset($matches[2]) ? (int)$matches[2] : 0;
                    if ($matches[3] === 'PM' && $hour !== 12) {
                        $hour += 12;
                    } elseif ($matches[3] === 'AM' && $hour === 12) {
                        $hour = 0;
                    }
                    $notifyThresholdTime = sprintf('%02d:%02d:00', $hour, $minute);
                }
            }

            $prepingAttrs = $this->prepingAttributesFromRequest($request);

            $updateData = [
                'label' => trim($request->label),
                'description' => $request->description ? trim($request->description) : null,
                'idCompany' => (int)$request->idCompany,
                'feedType' => $request->feedType,
                'postUrl' => trim($request->postUrl),
                'staticFieldsJSON' => $staticFieldsJSON,
                'varFieldsJSON' => $varFieldsJSON,
                'fieldMap' => $fieldMap,
                'cron' => $request->has('cron') && ($request->cron === '1' || $request->cron === true) ? '1' : '0',
                'cronTiming' => $request->cronTiming ? (int)$request->cronTiming : 1,
                'successString' => $request->successString ? trim($request->successString) : null,
                'throttle' => $request->throttle ? (int)$request->throttle : 100,
                'urlassignments' => $request->has('urlassignments') ? $urlassignments : $feed->urlassignments,
                'dailyLimit' => $request->dailyLimit ? (int)$request->dailyLimit : null,
                'delay' => $request->delay ? (int)$request->delay : null,
                'status' => $request->status ?? 'active',
                'feedCategory' => $request->feedCategory ?? 'email',
                'responseType' => $this->normalizeResponseType($request->input('responseType')),
                'webhookSecret' => $request->webhookSecret ? trim($request->webhookSecret) : null,
                'delayDump' => $request->has('delayDump') && ($request->delayDump === '1' || $request->delayDump === true) ? 1 : 0,
                'notifyThresholdCount' => $request->notifyThresholdCount ? (int)$request->notifyThresholdCount : 0,
                'notifyThresholdTime' => $notifyThresholdTime,
                'notifyThresholdDays' => $notifyThresholdDays,
                'revenuePerLead' => $request->revenuePerLead ? (float)$request->revenuePerLead : 0.0000,
                'launchDate' => $request->launchDate ? $request->launchDate : null,
                'costPerLeadOverride' => $request->costPerLeadOverride ? (float)$request->costPerLeadOverride : null,
                'costKey' => $request->costKey ? trim($request->costKey) : null,
                'valueMap' => $valueMap,
                'salesperson' => $request->salesperson ? (int)$request->salesperson : null,
                'xmlDTD' => $request->xmlDTD ? trim($request->xmlDTD) : null,
                'processingSchedule' => $request->has('processingSchedule') ? $processingSchedule : $feed->processingSchedule,
                'timezone' => $request->timezone ?? 'UTC',
                'leadStatus' => $request->leadStatus ? trim($request->leadStatus) : null,
                'prepingEnabled' => $prepingAttrs['prepingEnabled'],
                'prepingUrl' => $prepingAttrs['prepingUrl'],
                'prepingHttpMethod' => $prepingAttrs['prepingHttpMethod'],
                'prepingAuthType' => $prepingAttrs['prepingAuthType'],
                'prepingAuthValue' => $prepingAttrs['prepingAuthValue'],
            ];
            $feed->update($updateData);

            return response()->json([
                'status' => 1,
                'error' => 'Successfully updated outgoing feed.',
                'data' => [
                    'idFeedOut' => $feed->idFeedOut,
                    'label' => $feed->label,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $firstError = reset($errors);
            return response()->json([
                'status' => 0,
                'error' => is_array($firstError) ? $firstError[0] : 'Validation failed.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Failed when trying to update outgoing feed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get feed categories
     */
    public function getCategories()
    {
        return response()->json([
            'status' => 1,
            'data' => [
                'email' => 'Email',
                'phone' => 'Phone',
                'phone-preping' => 'Phone Pre-Ping',
            ],
        ]);
    }

    /**
     * Get feed types
     */
    public function getFeedTypes()
    {
        return response()->json([
            'status' => 1,
            'data' => [
                'curlPOST' => 'cURL POST',
                'curlGET' => 'cURL GET',
                'JSON' => 'JSON',
                'csvString' => 'CSV String',
                'soapPOST' => 'SOAP POST',
                'curlPOST-urlencoded' => 'cURL POST (URL Encoded)',
                'xmlPOST' => 'XML POST',
            ],
        ]);
    }

    /**
     * Get available mappable fields for outbound feeds
     */
    public function getAvailableFields()
    {
        try {
            $fields = DB::table('fields')
                ->whereIn('fieldType', ['system', 'custom', 'outbound', 'outbound-export'])
                ->orderByRaw("REPLACE(fieldName,'c_','')")
                ->get()
                ->map(function ($field) {
                    return [
                        'fieldName' => $field->fieldName,
                        'fieldDescription' => $field->fieldDescription,
                    ];
                });

            return response()->json([
                'status' => 1,
                'data' => $fields,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch available fields: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get timezones
     */
    public function getTimezones()
    {
        try {
            $timezones = \DateTimeZone::listIdentifiers();
            return response()->json([
                'status' => 1,
                'data' => $timezones,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch timezones: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Create a new outbound feed
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'label' => 'required|string|max:30',
                'idCompany' => 'required|integer',
                'feedType' => 'required|string|in:curlPOST,curlGET,JSON,csvString,soapPOST,curlPOST-urlencoded,xmlPOST',
                'postUrl' => 'required|string|max:1000',
                'timezone' => 'required|string',
                'prepingEnabled' => 'sometimes|boolean',
                'prepingUrl' => [
                    Rule::requiredIf(fn () => $request->boolean('prepingEnabled')),
                    'nullable',
                    'string',
                    'max:1000',
                ],
                'prepingHttpMethod' => 'nullable|string|in:GET,POST',
                'prepingAuthType' => 'nullable|string|in:none,bearer,basic',
                'prepingAuthValue' => [
                    Rule::requiredIf(fn () => $request->boolean('prepingEnabled')
                        && in_array($request->input('prepingAuthType', 'none'), ['bearer', 'basic'], true)),
                    'nullable',
                    'string',
                ],
            ]);

            // Process staticFieldsJSON
            $staticFieldsJSON = null;
            if ($request->has('staticFields') && is_array($request->staticFields)) {
                $staticFields = [];
                foreach ($request->staticFields as $field) {
                    if (!empty($field['field']) && isset($field['value'])) {
                        $staticFields[trim($field['field'])] = trim($field['value']);
                    }
                }
                if (!empty($staticFields)) {
                    $staticFieldsJSON = $staticFields;
                }
            }

            // Process varFieldsJSON and fieldMap
            $varFieldsJSON = null;
            $fieldMap = null;
            if ($request->has('varFields') && is_array($request->varFields)) {
                $varFields = [];
                $fieldMapArray = [];
                foreach ($request->varFields as $field) {
                    if (!empty($field['field']) && isset($field['map'])) {
                        $varFields[trim($field['field'])] = trim($field['map']);
                        $fieldMapArray[trim($field['field'])] = trim($field['map']);
                    }
                }
                if (!empty($varFields)) {
                    $varFieldsJSON = $varFields;
                    $fieldMap = json_encode($fieldMapArray);
                }
            }

            // Process valueMap
            $valueMap = null;
            if ($request->has('valueMap') && is_array($request->valueMap)) {
                $valueMapArray = [];
                foreach ($request->valueMap as $map) {
                    if (!empty($map['field']) && isset($map['oldValue']) && isset($map['newValue'])) {
                        $valueMapArray[] = [
                            'field' => trim($map['field']),
                            'oldValue' => trim($map['oldValue']),
                            'newValue' => trim($map['newValue']),
                        ];
                    }
                }
                if (!empty($valueMapArray)) {
                    $valueMap = json_encode($valueMapArray);
                }
            }

            $prepingAttrs = $this->prepingAttributesFromRequest($request);

            // Process notifyThresholdDays
            $notifyThresholdDays = null;
            if ($request->has('notifyThresholdDays') && is_array($request->notifyThresholdDays) && !empty($request->notifyThresholdDays)) {
                $notifyThresholdDays = implode(',', $request->notifyThresholdDays);
            }

            // Process urlassignments from array format (store)
            $urlassignments = null;
            if ($request->has('urlassignments') && is_array($request->urlassignments)) {
                $pairs = [];
                foreach ($request->urlassignments as $item) {
                    if (!empty($item['url']) || !empty($item['id'])) {
                        $url = str_replace(['=', ';'], '', trim($item['url'] ?? ''));
                        $id = str_replace(['=', ';'], '', trim($item['id'] ?? ''));
                        $pairs[] = $url . '=' . $id;
                    }
                }
                $urlassignments = !empty($pairs) ? implode(';', $pairs) : null;
            } elseif ($request->urlassignments && is_string($request->urlassignments)) {
                $urlassignments = trim($request->urlassignments) ?: null;
            }

            // Process processingSchedule (store)
            $processingSchedule = null;
            if ($request->has('processingSchedule') && is_array($request->processingSchedule)) {
                $schedule = [];
                $days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
                foreach ($days as $day) {
                    $dayData = $request->processingSchedule[$day] ?? ['enabled' => true, 'startTime' => '', 'endTime' => ''];
                    $schedule[$day] = [
                        'enabled' => !empty($dayData['enabled']),
                        'startTime' => $dayData['startTime'] ?? '',
                        'endTime' => $dayData['endTime'] ?? '',
                    ];
                }
                $processingSchedule = json_encode($schedule);
            }

            // Process notifyThresholdTime
            $notifyThresholdTime = null;
            if ($request->has('notifyThresholdTime') && !empty($request->notifyThresholdTime)) {
                $timeStr = $request->notifyThresholdTime;
                $timeStr = strtoupper($timeStr);
                $timeStr = preg_replace('/\s+/', '', $timeStr);
                if (preg_match('/(\d{1,2}):?(\d{2})?\s*(AM|PM)/', $timeStr, $matches)) {
                    $hour = (int)$matches[1];
                    $minute = isset($matches[2]) ? (int)$matches[2] : 0;
                    if ($matches[3] === 'PM' && $hour !== 12) {
                        $hour += 12;
                    } elseif ($matches[3] === 'AM' && $hour === 12) {
                        $hour = 0;
                    }
                    $notifyThresholdTime = sprintf('%02d:%02d:00', $hour, $minute);
                }
            }

            $feed = OutboundFeed::create([
                'label' => trim($request->label),
                'description' => $request->description ? trim($request->description) : null,
                'idCompany' => (int)$request->idCompany,
                'feedType' => $request->feedType,
                'postUrl' => trim($request->postUrl),
                'staticFieldsJSON' => $staticFieldsJSON,
                'varFieldsJSON' => $varFieldsJSON,
                'fieldMap' => $fieldMap,
                'cron' => $request->has('cron') && ($request->cron === '1' || $request->cron === true) ? '1' : '0',
                'cronTiming' => $request->cronTiming ? (int)$request->cronTiming : 1,
                'successString' => $request->successString ? trim($request->successString) : null,
                'throttle' => $request->throttle ? (int)$request->throttle : 100,
                'urlassignments' => $urlassignments,
                'dailyLimit' => $request->dailyLimit ? (int)$request->dailyLimit : null,
                'delay' => $request->delay ? (int)$request->delay : null,
                'queued' => 0,
                'status' => $request->status ?? 'active',
                'feedCategory' => $request->feedCategory ?? 'email',
                'responseType' => $this->normalizeResponseType($request->input('responseType')),
                'webhookSecret' => $request->webhookSecret ? trim($request->webhookSecret) : null,
                'delayDump' => $request->has('delayDump') && ($request->delayDump === '1' || $request->delayDump === true) ? 1 : 0,
                'notifyThresholdCount' => $request->notifyThresholdCount ? (int)$request->notifyThresholdCount : 0,
                'notifyThresholdTime' => $notifyThresholdTime,
                'notifyThresholdDays' => $notifyThresholdDays,
                'revenuePerLead' => $request->revenuePerLead ? (float)$request->revenuePerLead : 0.0000,
                'launchDate' => $request->launchDate ? $request->launchDate : null,
                'costPerLeadOverride' => $request->costPerLeadOverride ? (float)$request->costPerLeadOverride : null,
                'costKey' => $request->costKey ? trim($request->costKey) : null,
                'valueMap' => $valueMap,
                'salesperson' => $request->salesperson ? (int)$request->salesperson : null,
                'xmlDTD' => $request->xmlDTD ? trim($request->xmlDTD) : null,
                'processingSchedule' => $processingSchedule,
                'timezone' => $request->timezone ?? 'UTC',
                'leadStatus' => $request->leadStatus ? trim($request->leadStatus) : null,
                'prepingEnabled' => $prepingAttrs['prepingEnabled'],
                'prepingUrl' => $prepingAttrs['prepingUrl'],
                'prepingHttpMethod' => $prepingAttrs['prepingHttpMethod'],
                'prepingAuthType' => $prepingAttrs['prepingAuthType'],
                'prepingAuthValue' => $prepingAttrs['prepingAuthValue'],
            ]);

            return response()->json([
                'status' => 1,
                'error' => 'Successfully added new outgoing feed.',
                'data' => [
                    'idFeedOut' => $feed->idFeedOut,
                    'label' => $feed->label,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $firstError = reset($errors);
            return response()->json([
                'status' => 0,
                'error' => is_array($firstError) ? $firstError[0] : 'Validation failed.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Failed when trying to add a new outgoing feed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a test record to an outbound feed
     */
    public function sendTestRecord(Request $request, $id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }

            $testData = $request->only([
                'email', 'fname', 'lname', 'addr', 'addr2', 'city', 'state', 'zip', 'country',
                'cellphone', 'landline', 'gender', 'dob', 'ip', 'url', 'listcode', 'leadId',
                'custom1', 'custom2', 'custom3', 'custom4', 'custom5', 'custom6',
            ]);

            $result = OutboundTestService::sendTest($feed, $testData);

            return response()->json([
                'status' => $result['status'] ? 1 : 0,
                'success' => $result['status'],
                'headers' => $result['headers'] ?? [],
                'querystring' => $result['querystring'] ?? '',
                'text' => $result['text'] ?? '',
                'statusCode' => $result['statusCode'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get queue preview for an outbound feed
     */
    public function queuePreview($id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }

            $stats = DB::table('data_outbound')
                ->join('data_inbound', 'data_inbound.idRecord', '=', 'data_outbound.idRecord')
                ->where('data_outbound.idFeedOut', $id)
                ->where('data_outbound.processed', 0)
                ->selectRaw('LEFT(data_inbound.timestamp, 10) AS date, COUNT(*) AS cnt')
                ->groupBy(DB::raw('LEFT(data_inbound.timestamp, 10)'))
                ->get();

            return response()->json(['status' => 1, 'data' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Clear queue for an outbound feed (submits job)
     */
    public function clearQueue($id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }

            $jobId = $this->addLegacyJob('clear-outbound-queue', $id, ['label' => $feed->label]);
            if ($jobId) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Clear outbound queue job #' . $jobId . ' submitted successfully.',
                ]);
            }

            return response()->json([
                'status' => 0,
                'message' => 'Could not add job to database. The legacy job processor may need to be running.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get URL list for outbound feed (for URL report dropdown)
     */
    public function getUrlList($id)
    {
        try {
            $urls = DB::table('stats_outbound')
                ->where('idFeedOut', $id)
                ->select('url')
                ->selectRaw('MIN(stamp) as date')
                ->groupBy('url')
                ->get();

            return response()->json(['status' => 1, 'data' => $urls]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Get URL report for outbound feed
     */
    public function getUrlReport(Request $request, $id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }

            $dateStart = $request->input('dateStart');
            $dateEnd = $request->input('dateEnd');
            $urlList = $request->input('urlList', []);
            $breakdown = $request->input('breakdown', 'day');
            $sort = $request->input('sort', 'date');
            $group = $request->input('group', 'date');

            $query = DB::table('stats_outbound')->where('idFeedOut', $id);

            if (!empty($urlList) && is_array($urlList)) {
                $query->whereIn('url', $urlList);
            }
            if (!empty($dateStart) && !empty($dateEnd)) {
                $query->whereBetween('stamp', [$dateStart, $dateEnd]);
            }

            $dateSelect = $breakdown === 'month' ? 'LEFT(stamp, 7)' : ($breakdown === 'year' ? 'LEFT(stamp, 4)' : ($breakdown === 'total' ? "'TOTAL'" : 'stamp'));

            if ($group === 'date') {
                $urlSelect = "'N/A' as url";
                $groupBy = [DB::raw($dateSelect)];
            } else {
                $urlSelect = 'url';
                $groupBy = $breakdown === 'total' ? ['url'] : ['url', DB::raw($dateSelect)];
            }

            $results = (clone $query)->selectRaw("{$urlSelect}, {$dateSelect} as date, SUM(accepted) as accepted, SUM(rejected) as rejected")
                ->groupBy($groupBy);
            if ($sort === 'url') {
                $results->orderBy('url');
            } elseif ($sort === 'count') {
                $results->orderByRaw('SUM(accepted) + SUM(rejected) DESC');
            } else {
                $results->orderBy('date');
            }
            $results = $results->get();

            return response()->json(['status' => 1, 'data' => $results]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Get export columns for outbound feed
     */
    public function getExportColumns($id)
    {
        try {
            $fields = DB::table('fields')
                ->whereIn('fieldType', ['system', 'custom', 'outbound', 'outbound-export'])
                ->whereNotIn('fieldName', ['authorization', 'pswd'])
                ->orderByRaw("REPLACE(fieldName,'c_','')")
                ->get(['fieldName', 'fieldDescription']);

            return response()->json(['status' => 1, 'data' => $fields]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Create export for outbound feed (stream CSV)
     */
    public function createExport(Request $request, $id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }
            $columns = $request->input('columns', []);
            if (empty($columns) || !is_array($columns)) {
                return response()->json(['status' => 0, 'error' => 'Select at least one column to export'], 422);
            }
            $dateStart = $request->input('dateStart', date('Y-m-d'));
            $dateEnd = $request->input('dateEnd', date('Y-m-d', strtotime('tomorrow')));
            $limit = $request->input('limit');
            $includeRejects = (bool) $request->input('includeRejects', false);

            $inboundCols = ['idRecord', 'idFeedIn', 'timestamp', 'leadstamp', 'listcode', 'url', 'ip', 'email', 'fname', 'lname', 'addr', 'addr2', 'city', 'state', 'zip', 'country', 'dob', 'gender', 'landline', 'cellphone', 'result', 'leadId', 'custom1', 'custom2', 'custom3', 'custom4', 'custom5', 'custom6'];
            $outboundCols = ['idFeedOut'];
            $selectCols = [];
            $headers = [];
            foreach ($columns as $col) {
                $dbCol = $col === 'stamp' ? 'leadstamp' : $col;
                if (in_array($dbCol, $inboundCols)) {
                    $selectCols[] = 'data_inbound.' . $dbCol . ' as ' . $dbCol;
                    $headers[] = $col === 'stamp' ? 'stamp' : $dbCol;
                } elseif (in_array($dbCol, $outboundCols)) {
                    $selectCols[] = 'data_outbound.' . $dbCol . ' as ' . $dbCol;
                    $headers[] = $dbCol;
                }
            }
            if (empty($selectCols)) {
                return response()->json(['status' => 0, 'error' => 'No valid columns selected'], 422);
            }

            $query = DB::table('data_outbound')
                ->join('data_inbound', 'data_inbound.idRecord', '=', 'data_outbound.idRecord')
                ->where('data_outbound.idFeedOut', $id)
                ->whereBetween(DB::raw('DATE(data_inbound.timestamp)'), [$dateStart, $dateEnd])
                ->selectRaw(implode(', ', $selectCols));

            if (!$includeRejects) {
                $query->where('data_outbound.accepted', 1);
            }
            if ($limit) {
                $query->limit((int) $limit);
            }

            $rows = $query->get();

            $output = fopen('php://temp', 'r+');
            fputcsv($output, $headers);
            foreach ($rows as $row) {
                fputcsv($output, (array) $row);
            }
            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);

            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="outbound_' . $id . '_' . date('YmdHis') . '.csv"',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create import job for outbound feed
     */
    public function createImport(Request $request, $id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }
            $dateStart = $request->input('dateStart');
            $dateEnd = $request->input('dateEnd');
            if (!$dateStart || !$dateEnd) {
                return response()->json(['status' => 0, 'error' => 'Date range is required'], 422);
            }
            $jobId = $this->addLegacyJob('import-legacy-outbound', $id, [
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
            ]);
            if ($jobId) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Import job #' . $jobId . ' submitted successfully.',
                ]);
            }
            return response()->json(['status' => 0, 'message' => 'Could not add job.'], 500);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create upload job for outbound feed
     */
    public function createUpload(Request $request, $id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }
            $file = $request->file('file');
            if (!$file || !$file->isValid()) {
                return response()->json(['status' => 0, 'error' => 'Valid file is required'], 422);
            }
            $path = $file->store('imports/outbound', 'local');
            $jobId = $this->addLegacyJob('import-legacy-outbound', $id, ['upload' => true], storage_path('app/' . $path), 0);
            if ($jobId) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Upload job #' . $jobId . ' submitted successfully.',
                ]);
            }
            return response()->json(['status' => 0, 'message' => 'Could not add job.'], 500);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retry rejections for outbound feed
     */
    public function retryRejections(Request $request, $id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }
            $dateStart = $request->input('dateStart');
            $dateEnd = $request->input('dateEnd');
            if (!$dateStart || !$dateEnd) {
                return response()->json(['status' => 0, 'error' => 'Date range is required'], 422);
            }

            RetryOutboundRejectionsJob::dispatch((int) $id, (string) $dateStart, (string) $dateEnd);

            return response()->json([
                'status' => 1,
                'message' => 'Retry rejections job submitted to Laravel queue successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Submit async job to resend all pending marketplace leads for one outbound feed.
     */
    public function resendPendingMarketplace(Request $request, $id)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }
            if (($feed->responseType ?? 'realtime') !== 'marketplace') {
                return response()->json(['status' => 0, 'error' => 'Resend pending is allowed only for marketplace feeds'], 422);
            }

            $chunkSize = (int) $request->input('chunkSize', 200);
            if ($chunkSize < 1) {
                $chunkSize = 200;
            }
            if ($chunkSize > 1000) {
                $chunkSize = 1000;
            }

            $batch = Bus::batch([
                new ResendPendingMarketplaceJob((int) $id, $chunkSize),
            ])->name('resend-pending-marketplace-' . (int) $id)
                ->allowFailures()
                ->dispatch();

            Cache::put($this->resendPendingMarketplaceCacheKey($batch->id), [
                'summary' => [
                    'total' => 0,
                    'accepted' => 0,
                    'rejected' => 0,
                    'pending_manual' => 0,
                    'pending_webhook' => 0,
                    'errors' => 0,
                ],
                'message' => null,
                'submittedAt' => now()->toDateTimeString(),
            ], now()->addDays(7));

            return response()->json([
                'status' => 1,
                'jobId' => $batch->id,
                'message' => 'Resend pending marketplace job submitted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get async job status/result for resend pending marketplace.
     */
    public function resendPendingMarketplaceStatus(Request $request, $id, $jobId)
    {
        try {
            $feed = OutboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }

            $batch = Bus::findBatch((string) $jobId);
            if (!$batch) {
                return response()->json(['status' => 0, 'error' => 'Job not found'], 404);
            }
            $meta = Cache::get($this->resendPendingMarketplaceCacheKey((string) $jobId), []);
            $summary = is_array($meta['summary'] ?? null) ? $meta['summary'] : [];
            $normalizedSummary = [
                'total' => (int) ($summary['total'] ?? 0),
                'accepted' => (int) ($summary['accepted'] ?? 0),
                'rejected' => (int) ($summary['rejected'] ?? 0),
                'pending_manual' => (int) ($summary['pending_manual'] ?? 0),
                'pending_webhook' => (int) ($summary['pending_webhook'] ?? 0),
                'errors' => (int) ($summary['errors'] ?? 0),
            ];
            $jobStatus = 'pending';
            if ($batch->cancelled()) {
                $jobStatus = 'cancelled';
            } elseif ($batch->finished()) {
                $jobStatus = $batch->failedJobs > 0 ? 'error' : 'finished';
            } elseif ($batch->pendingJobs < $batch->totalJobs) {
                $jobStatus = 'processing';
            }

            return response()->json([
                'status' => 1,
                'data' => [
                    'jobId' => (string) $jobId,
                    'feedId' => (int) $id,
                    'jobStatus' => $jobStatus,
                    'records' => (int) (($summary['accepted'] ?? 0) + ($summary['rejected'] ?? 0)),
                    'message' => $meta['message'] ?? null,
                    'summary' => $normalizedSummary,
                    'submittedAt' => $meta['submittedAt'] ?? null,
                    'progress' => (int) $batch->progress(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    private function resendPendingMarketplaceCacheKey(string $jobId): string
    {
        return 'resend-pending-marketplace:' . $jobId;
    }

    /**
     * Ensure responseType is always a valid non-null ENUM value for MySQL.
     */
    private function normalizeResponseType(mixed $value): string
    {
        if (is_string($value) && in_array($value, ['realtime', 'marketplace'], true)) {
            return $value;
        }

        return 'realtime';
    }

    /**
     * Normalize preping columns from request (store/update).
     *
     * @return array{prepingEnabled: bool, prepingUrl: ?string, prepingHttpMethod: string, prepingAuthType: string, prepingAuthValue: ?string}
     */
    private function prepingAttributesFromRequest(Request $request): array
    {
        $prepingEnabled = $request->boolean('prepingEnabled');
        $method = strtoupper((string) $request->input('prepingHttpMethod', 'POST'));
        if (!in_array($method, ['GET', 'POST'], true)) {
            $method = 'POST';
        }
        $authType = $request->input('prepingAuthType', 'none');
        if (!in_array($authType, ['none', 'bearer', 'basic'], true)) {
            $authType = 'none';
        }
        $authValue = null;
        if (in_array($authType, ['bearer', 'basic'], true) && $request->filled('prepingAuthValue')) {
            $authValue = trim((string) $request->prepingAuthValue);
        }

        return [
            'prepingEnabled' => $prepingEnabled,
            'prepingUrl' => $request->filled('prepingUrl') ? trim((string) $request->prepingUrl) : null,
            'prepingHttpMethod' => $method,
            'prepingAuthType' => $authType,
            'prepingAuthValue' => $authValue,
        ];
    }

    /**
     * Add job to legacy jobs table (if it exists with legacy schema)
     */
    private function addLegacyJob(string $type, int $destination, array $fields, string $filename = '', int $records = 0): ?int
    {
        try {
            if (!Schema::hasTable('jobs')) {
                throw new \RuntimeException('Jobs table does not exist.');
            }

            $requiredColumns = ['type', 'destination', 'fields', 'filename', 'records', 'idUser', 'status'];
            $missingColumns = array_values(array_filter($requiredColumns, fn ($col) => !Schema::hasColumn('jobs', $col)));
            if (!empty($missingColumns)) {
                throw new \RuntimeException(
                    'Jobs table is missing legacy columns: ' . implode(', ', $missingColumns)
                );
            }

            $userId = auth()->id() ?? 0;
            $insertData = [
                'type' => $type,
                'destination' => $destination,
                'fields' => serialize($fields),
                'filename' => $filename,
                'records' => $records,
                'idUser' => $userId,
                'status' => 'pending',
            ];

            // Compatibility with Laravel queue jobs schema.
            if (Schema::hasColumn('jobs', 'queue')) {
                $insertData['queue'] = 'legacy';
            }
            if (Schema::hasColumn('jobs', 'payload')) {
                $insertData['payload'] = json_encode([
                    'type' => $type,
                    'destination' => $destination,
                    'fields' => $fields,
                    'filename' => $filename,
                    'records' => $records,
                    'idUser' => $userId,
                    'status' => 'pending',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            if (Schema::hasColumn('jobs', 'attempts')) {
                $insertData['attempts'] = 0;
            }
            if (Schema::hasColumn('jobs', 'reserved_at')) {
                $insertData['reserved_at'] = null;
            }
            if (Schema::hasColumn('jobs', 'available_at')) {
                $insertData['available_at'] = time();
            }
            if (Schema::hasColumn('jobs', 'created_at')) {
                $insertData['created_at'] = time();
            }

            DB::table('jobs')->insert($insertData);
            $lastInsertId = DB::getPdo()->lastInsertId();
            if (is_numeric($lastInsertId) && (int) $lastInsertId > 0) {
                return (int) $lastInsertId;
            }

            // Fallback for legacy schemas where PDO lastInsertId can be empty.
            if (Schema::hasColumn('jobs', 'jobId')) {
                $row = DB::table('jobs')
                    ->where('type', $type)
                    ->where('destination', $destination)
                    ->where('idUser', $userId)
                    ->orderByDesc('jobId')
                    ->first(['jobId']);
                if (!empty($row?->jobId)) {
                    return (int) $row->jobId;
                }
            }

            return 1;
        } catch (\Exception $e) {
            Log::channel('single')->error('[OutboundFeed] addLegacyJob failed', [
                'type' => $type,
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
