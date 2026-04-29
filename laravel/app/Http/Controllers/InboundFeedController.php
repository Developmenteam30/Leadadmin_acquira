<?php

namespace App\Http\Controllers;

use App\Models\InboundFeed;
use App\Models\Company;
use App\Helpers\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InboundFeedController extends Controller
{
    /**
     * Get inbound feeds grouped by company
     */
    public function index(Request $request)
    {
        try {
            $status = $request->input('status');
            $feedCategory = $request->input('feedCategory', 'phone'); // Default to phone based on image
            $statsStart = $request->input('statsStart', date('Y-m-d', strtotime('-30 days')));
            $statsEnd = $request->input('statsEnd', date('Y-m-d'));

            $query = DB::table('feedinc')
                ->leftJoin('companies', 'feedinc.idCompany', '=', 'companies.idCompany')
                ->select('feedinc.*', 'companies.name as companyName');

            if ($status) {
                $query->where('feedinc.status', $status);
            }

            if ($feedCategory) {
                $query->where('feedinc.feedCategory', $feedCategory);
            }

            CompanyScope::apply($query, $request->user(), 'feedinc.idCompany');

            return $this->getFeedsGroupedByCompany($query, $statsStart, $statsEnd);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch incoming feeds: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get ping requests (phone-preping feeds) grouped by company
     */
    public function ping(Request $request)
    {
        try {
            $status = $request->input('status');
            $statsStart = $request->input('statsStart', date('Y-m-d', strtotime('-30 days')));
            $statsEnd = $request->input('statsEnd', date('Y-m-d'));

            $query = DB::table('feedinc')
                ->leftJoin('companies', 'feedinc.idCompany', '=', 'companies.idCompany')
                ->select('feedinc.*', 'companies.name as companyName')
                ->where('feedinc.feedCategory', 'phone-preping'); // Only ping feeds

            if ($status) {
                $query->where('feedinc.status', $status);
            }

            CompanyScope::apply($query, $request->user(), 'feedinc.idCompany');

            return $this->getFeedsGroupedByCompany($query, $statsStart, $statsEnd);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch ping requests: ' . $e->getMessage(),
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
                ->orderBy('feedinc.idFeedIn')
                ->get();

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
                        'totalAccepted' => 0,
                        'totalRejected' => 0,
                        'totalPending' => 0,
                    ];
                }

                // Get stats for this feed
                $stats = $this->getFeedStats($feed->idFeedIn, $statsStart, $statsEnd);
                
                $feedData = [
                    'idFeedIn' => $feed->idFeedIn,
                    'label' => $feed->label ?? '',
                    'description' => $feed->description ?? '',
                    'status' => $feed->status ?? 'active',
                    'paused' => $feed->paused ?? 0,
                    'accepted' => $stats['accepted'],
                    'rejected' => $stats['rejected'],
                    'pending' => $stats['pending'],
                ];

                $companyGroups[$companyId]['feeds'][] = $feedData;
                $companyGroups[$companyId]['totalAccepted'] += $stats['accepted'];
                $companyGroups[$companyId]['totalRejected'] += $stats['rejected'];
                $companyGroups[$companyId]['totalPending'] += $stats['pending'];
            }

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
     * Accepted = sent to at least one outgoing feed; Pending = not sent or awaiting webhook; Rejected = rejection message
     */
    private function getFeedStats($idFeedIn, $startDate, $endDate)
    {
        try {
            $tsStart = $startDate . ' 00:00:00';
            $tsEnd = $endDate . ' 23:59:59';

            $acceptedCount = DB::table('data_inbound as i')
                ->where('i.idFeedIn', $idFeedIn)
                ->whereBetween('i.timestamp', [$tsStart, $tsEnd])
                ->where(function ($q) {
                    $q->whereNull('i.result')->orWhere('i.result', '')->orWhere('i.result', 'Success');
                })
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('data_outbound as o')
                        ->whereColumn('o.idRecord', 'i.idRecord')
                        ->whereColumn('o.idFeedIn', 'i.idFeedIn');
                })
                ->count();

            $rejectedCount = DB::table('data_inbound as i')
                ->where('i.idFeedIn', $idFeedIn)
                ->whereBetween('i.timestamp', [$tsStart, $tsEnd])
                ->whereNotNull('i.result')
                ->where('i.result', '!=', '')
                ->where('i.result', '!=', 'Success')
                ->where('i.result', '!=', 'Pending')
                ->count();

            $pendingCount = DB::table('data_inbound as i')
                ->where('i.idFeedIn', $idFeedIn)
                ->whereBetween('i.timestamp', [$tsStart, $tsEnd])
                ->where(function ($q) {
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
                })
                ->count();

            return [
                'accepted' => (int) $acceptedCount,
                'rejected' => (int) $rejectedCount,
                'pending' => (int) $pendingCount,
            ];
        } catch (\Exception $e) {
            return [
                'accepted' => 0,
                'rejected' => 0,
                'pending' => 0,
            ];
        }
    }

    /**
     * Get a single inbound feed
     */
    public function show($id)
    {
        try {
            $feed = InboundFeed::with('company')->find($id);

            if (!$feed) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Feed not found',
                ], 404);
            }

            // Parse semicolon-separated fields
            $feed->required = !empty($feed->required) ? explode(';', $feed->required) : [];
            $feed->allowedFields = !empty($feed->allowedFields) ? explode(';', $feed->allowedFields) : [];
            $feed->requiredPingFields = !empty($feed->requiredPingFields) ? explode(';', $feed->requiredPingFields) : [];
            $feed->allowedPingFields = !empty($feed->allowedPingFields) ? explode(';', $feed->allowedPingFields) : [];

            // Parse JSON fields
            if (!empty($feed->filterState)) {
                $filterStateData = is_string($feed->filterState) ? json_decode($feed->filterState, true) : $feed->filterState;
                $feed->filterState = $filterStateData ?: ['mode' => '', 'states' => []];
            } else {
                $feed->filterState = ['mode' => '', 'states' => []];
            }

            if (!empty($feed->filterZip)) {
                $filterZipData = is_string($feed->filterZip) ? json_decode($feed->filterZip, true) : $feed->filterZip;
                $feed->filterZip = $filterZipData ?: ['mode' => '', 'zipCodes' => []];
            } else {
                $feed->filterZip = ['mode' => '', 'zipCodes' => []];
            }

            // Parse filterUrl
            if (!empty($feed->filterUrl)) {
                $feed->filterUrl = is_array($feed->filterUrl) ? $feed->filterUrl : explode(';', $feed->filterUrl);
            } else {
                $feed->filterUrl = [];
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

            // Convert ENUM fields to strings
            $feed->dedupeEmail = $feed->dedupeEmail === '1' || $feed->dedupeEmail === 1 ? '1' : '0';
            $feed->dedupeLandline = $feed->dedupeLandline === '1' || $feed->dedupeLandline === 1 ? '1' : '0';
            $feed->dedupeCellphone = $feed->dedupeCellphone === '1' || $feed->dedupeCellphone === 1 ? '1' : '0';
            $feed->notifications = $feed->notifications === '1' || $feed->notifications === 1 ? '1' : '0';

            // Format notifyThresholdTime if it exists
            if (!empty($feed->notifyThresholdTime)) {
                $time = is_string($feed->notifyThresholdTime) ? $feed->notifyThresholdTime : $feed->notifyThresholdTime->format('H:i:s');
                // Convert 24-hour format to 12-hour format for display
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
     * Toggle pause status of an inbound feed
     */
    public function togglePause(Request $request, $id)
    {
        try {
            $feed = InboundFeed::find($id);
            if (!$feed) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Feed not found',
                ], 404);
            }

            $paused = $request->input('paused');
            if ($paused === null || $paused === '') {
                return response()->json([
                    'status' => 0,
                    'error' => 'paused value is required (0 or 1)',
                ], 422);
            }

            $paused = filter_var($paused, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $feed->paused = $paused;
            $feed->save();

            return response()->json([
                'status' => 1,
                'data' => ['paused' => (bool) $paused],
                'message' => $paused ? 'Feed paused' : 'Feed enabled',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to toggle pause: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an inbound feed
     */
    public function update(Request $request, $id)
    {
        try {
            $feed = InboundFeed::find($id);
            if (!$feed) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Feed not found',
                ], 404);
            }

            $request->validate([
                'label' => 'required|string|max:255',
                'idCompany' => 'required|integer',
                'feedCategory' => 'required|string|in:email,phone,phone-preping',
                'allowedFields' => 'required|array|min:1',
            ]);

            // Validate required fields are also in allowed fields
            if ($request->has('required') && is_array($request->required)) {
                foreach ($request->required as $reqField) {
                    if ($reqField === 'phone') {
                        continue;
                    } elseif (!in_array($reqField, $request->allowedFields)) {
                        return response()->json([
                            'status' => 0,
                            'error' => "If {$reqField} is a required field, then that field must be allowed as well.",
                        ], 422);
                    }
                }
            }

            // Validate PING fields for phone-preping
            if ($request->feedCategory === 'phone-preping') {
                if (empty($request->allowedPingFields) || !is_array($request->allowedPingFields)) {
                    return response()->json([
                        'status' => 0,
                        'error' => 'You must allow at least one PING field to be processed.',
                    ], 422);
                }

                if ($request->has('requiredPingFields') && is_array($request->requiredPingFields)) {
                    foreach ($request->requiredPingFields as $reqPingField) {
                        if ($reqPingField === 'phone') {
                            if (!in_array('landline', $request->allowedPingFields) || !in_array('cellphone', $request->allowedPingFields)) {
                                return response()->json([
                                    'status' => 0,
                                    'error' => 'If phone is selected, both landline and cellphone must be allowed PING fields.',
                                ], 422);
                            }
                        } elseif (!in_array($reqPingField, $request->allowedPingFields)) {
                            return response()->json([
                                'status' => 0,
                                'error' => "If {$reqPingField} is a required PING field, then that field must be an allowed PING field as well.",
                            ], 422);
                        }
                    }
                }

                // Force authorization field for ping/post
                $allowedFields = $request->allowedFields;
                $requiredFields = $request->has('required') && is_array($request->required) ? $request->required : [];
                
                if (!in_array('authorization', $allowedFields)) {
                    $allowedFields[] = 'authorization';
                }
                if (!in_array('authorization', $requiredFields)) {
                    $requiredFields[] = 'authorization';
                }
                
                $request->merge([
                    'allowedFields' => $allowedFields,
                    'required' => $requiredFields,
                ]);
            } else {
                // Remove authorization field if not phone-preping
                $allowedFields = $request->allowedFields;
                $requiredFields = $request->has('required') && is_array($request->required) ? $request->required : [];
                
                $allowedFields = array_filter($allowedFields, function($field) {
                    return $field !== 'authorization';
                });
                $requiredFields = array_filter($requiredFields, function($field) {
                    return $field !== 'authorization';
                });
                
                $request->merge([
                    'allowedFields' => array_values($allowedFields),
                    'required' => array_values($requiredFields),
                ]);
            }

            // Process filterState
            $filterState = null;
            if ($request->has('filterState') && !empty($request->filterState)) {
                $filterState = json_encode([
                    'mode' => $request->filterState,
                    'states' => $request->input('filterStateChoice', []),
                ]);
            }

            // Process filterZip
            $filterZip = null;
            if ($request->has('filterZip') && !empty($request->filterZip)) {
                $filterZip = json_encode([
                    'mode' => $request->filterZip,
                    'zipCodes' => $request->input('filterZipCodes', []),
                ]);
            }

            // Process filterUrl
            $filterUrl = null;
            if ($request->has('filterTypeUrl') && !empty($request->filterTypeUrl)) {
                $filterUrlArray = $request->input('filterUrl', []);
                $filterUrl = is_array($filterUrlArray) ? implode(';', array_filter($filterUrlArray)) : null;
            }

            // Process notifyThresholdDays
            $notifyThresholdDays = null;
            if ($request->has('notifyThresholdDays') && is_array($request->notifyThresholdDays) && !empty($request->notifyThresholdDays)) {
                $notifyThresholdDays = implode(',', $request->notifyThresholdDays);
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

            // Process required fields - remove 'phone' if present
            $requiredFields = $request->has('required') && is_array($request->required) ? $request->required : [];
            $requiredFields = array_filter($requiredFields, function($field) {
                return $field !== 'phone';
            });
            $requiredFields = array_values($requiredFields);

            $feed->update([
                'label' => trim($request->label),
                'description' => $request->description ? trim($request->description) : null,
                'idCompany' => (int)$request->idCompany,
                'required' => implode(';', $requiredFields),
                'allowedFields' => implode(';', $request->allowedFields),
                'dedupeEmail' => ($request->dedupeEmail === '1' || $request->dedupeEmail === true || $request->dedupeEmail === 1) ? '1' : '0',
                'dedupeLandline' => ($request->dedupeLandline === '1' || $request->dedupeLandline === true || $request->dedupeLandline === 1) ? '1' : '0',
                'dedupeCellphone' => ($request->dedupeCellphone === '1' || $request->dedupeCellphone === true || $request->dedupeCellphone === 1) ? '1' : '0',
                'rejectOldLeads' => $request->has('rejectOldLeadsMaxAge') && !empty($request->rejectOldLeadsMaxAge) ? 1 : 0,
                'rejectOldLeadsMaxAge' => $request->rejectOldLeadsMaxAge ? trim($request->rejectOldLeadsMaxAge) : null,
                'dedupeAcross' => $request->dedupeAcross ? trim($request->dedupeAcross) : null,
                'filterTypeUrl' => $request->filterTypeUrl ? trim($request->filterTypeUrl) : null,
                'filterUrl' => $filterUrl,
                'notifications' => ($request->notifications === '1' || $request->notifications === true || $request->notifications === 1) ? '1' : '0',
                'status' => $request->status ?? 'active',
                'chokePercent' => $request->chokePercent ? (int)$request->chokePercent : 0,
                'feedCategory' => $request->feedCategory,
                'dailyLimit' => $request->dailyLimit ? (int)$request->dailyLimit : null,
                'custom1Label' => $request->custom1Label ? trim($request->custom1Label) : null,
                'custom2Label' => $request->custom2Label ? trim($request->custom2Label) : null,
                'custom3Label' => $request->custom3Label ? trim($request->custom3Label) : null,
                'custom4Label' => $request->custom4Label ? trim($request->custom4Label) : null,
                'custom5Label' => $request->custom5Label ? trim($request->custom5Label) : null,
                'custom6Label' => $request->custom6Label ? trim($request->custom6Label) : null,
                'costPerLead' => $request->costPerLead ? (float)$request->costPerLead : 0.00,
                'revenuePerLeadType' => in_array($request->revenuePerLeadType, ['fixed', 'percent'], true) ? $request->revenuePerLeadType : 'fixed',
                'revenuePerLead' => $request->revenuePerLead ? (float)$request->revenuePerLead : 0.00,
                'notifyThresholdCount' => $request->notifyThresholdCount ? (int)$request->notifyThresholdCount : 0,
                'notifyThresholdDays' => $notifyThresholdDays,
                'notifyThresholdTime' => $notifyThresholdTime,
                'salesperson' => $request->salesperson ? (int)$request->salesperson : null,
                'pauseMessage' => $request->pauseMessage ? trim($request->pauseMessage) : null,
                'timezone' => $request->timezone ?? 'America/New_York',
                'timeskew' => $request->timeskew ? trim($request->timeskew) : null,
                'filterState' => $filterState,
                'lookbackPeriod' => $request->lookbackPeriod ? (int)$request->lookbackPeriod : 90,
                'pingTimeout' => $request->pingTimeout ? (int)$request->pingTimeout : 300,
                'requiredPingFields' => $request->has('requiredPingFields') && is_array($request->requiredPingFields) ? implode(';', $request->requiredPingFields) : null,
                'allowedPingFields' => $request->has('allowedPingFields') && is_array($request->allowedPingFields) ? implode(';', $request->allowedPingFields) : null,
                'minimumBirthAge' => $request->minimumBirthAge ? (int)$request->minimumBirthAge : null,
                'maximumBirthAge' => $request->maximumBirthAge ? (int)$request->maximumBirthAge : null,
                'filterZip' => $filterZip,
            ]);

            return response()->json([
                'status' => 1,
                'error' => 'Successfully updated feed.',
                'data' => [
                    'idFeedIn' => $feed->idFeedIn,
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
                'error' => 'Failed when trying to update feed: ' . $e->getMessage(),
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
     * Get available fields for feeds
     */
    public function getAvailableFields()
    {
        try {
            $fields = DB::table('fields')
                ->whereIn('fieldType', ['system', 'custom', 'inbound-export'])
                ->whereNotIn('fieldName', ['authorization', 'pswd'])
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
     * Generate a random password for feed
     */
    private function generateFeedPassword()
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 16);
    }

    /**
     * Create a new inbound feed
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'label' => 'required|string|max:255',
                'idCompany' => 'required|integer',
                'feedCategory' => 'required|string|in:email,phone,phone-preping',
                'allowedFields' => 'required|array|min:1',
            ]);

            // Validate required fields are also in allowed fields
            if ($request->has('required') && is_array($request->required)) {
                foreach ($request->required as $reqField) {
                    if ($reqField === 'phone') {
                        // Skip phone validation as it's handled by checking landline and cellphone
                        continue;
                    } elseif (!in_array($reqField, $request->allowedFields)) {
                        return response()->json([
                            'status' => 0,
                            'error' => "If {$reqField} is a required field, then that field must be allowed as well.",
                        ], 422);
                    }
                }
            }

            // Validate PING fields for phone-preping
            if ($request->feedCategory === 'phone-preping') {
                if (empty($request->allowedPingFields) || !is_array($request->allowedPingFields)) {
                    return response()->json([
                        'status' => 0,
                        'error' => 'You must allow at least one PING field to be processed.',
                    ], 422);
                }

                if ($request->has('requiredPingFields') && is_array($request->requiredPingFields)) {
                    foreach ($request->requiredPingFields as $reqPingField) {
                        if ($reqPingField === 'phone') {
                            if (!in_array('landline', $request->allowedPingFields) || !in_array('cellphone', $request->allowedPingFields)) {
                                return response()->json([
                                    'status' => 0,
                                    'error' => 'If phone is selected, both landline and cellphone must be allowed PING fields.',
                                ], 422);
                            }
                        } elseif (!in_array($reqPingField, $request->allowedPingFields)) {
                            return response()->json([
                                'status' => 0,
                                'error' => "If {$reqPingField} is a required PING field, then that field must be an allowed PING field as well.",
                            ], 422);
                        }
                    }
                }

                // Force authorization field for ping/post - add to both allowed and required
                $allowedFields = $request->allowedFields;
                $requiredFields = $request->has('required') && is_array($request->required) ? $request->required : [];
                
                if (!in_array('authorization', $allowedFields)) {
                    $allowedFields[] = 'authorization';
                }
                if (!in_array('authorization', $requiredFields)) {
                    $requiredFields[] = 'authorization';
                }
                
                $request->merge([
                    'allowedFields' => $allowedFields,
                    'required' => $requiredFields,
                ]);
            } else {
                // Remove authorization field if not phone-preping
                $allowedFields = $request->allowedFields;
                $requiredFields = $request->has('required') && is_array($request->required) ? $request->required : [];
                
                $allowedFields = array_filter($allowedFields, function($field) {
                    return $field !== 'authorization';
                });
                $requiredFields = array_filter($requiredFields, function($field) {
                    return $field !== 'authorization';
                });
                
                $request->merge([
                    'allowedFields' => array_values($allowedFields),
                    'required' => array_values($requiredFields),
                ]);
            }

            // Process filterState
            $filterState = null;
            if ($request->has('filterState') && !empty($request->filterState)) {
                $filterState = json_encode([
                    'mode' => $request->filterState,
                    'states' => $request->input('filterStateChoice', []),
                ]);
            }

            // Process filterZip
            $filterZip = null;
            if ($request->has('filterZip') && !empty($request->filterZip)) {
                $filterZip = json_encode([
                    'mode' => $request->filterZip,
                    'zipCodes' => $request->input('filterZipCodes', []),
                ]);
            }

            // Process filterUrl
            $filterUrl = null;
            if ($request->has('filterTypeUrl') && !empty($request->filterTypeUrl)) {
                $filterUrlArray = $request->input('filterUrl', []);
                $filterUrl = is_array($filterUrlArray) ? implode(';', array_filter($filterUrlArray)) : null;
            }

            // Process notifyThresholdDays
            $notifyThresholdDays = null;
            if ($request->has('notifyThresholdDays') && is_array($request->notifyThresholdDays) && !empty($request->notifyThresholdDays)) {
                $notifyThresholdDays = implode(',', $request->notifyThresholdDays);
            }

            // Process notifyThresholdTime
            $notifyThresholdTime = null;
            if ($request->has('notifyThresholdTime') && !empty($request->notifyThresholdTime)) {
                // Convert time format (e.g., "10:00AM" to "10:00:00")
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

            // Process required fields - remove 'phone' if present and ensure landline/cellphone are included
            $requiredFields = $request->has('required') && is_array($request->required) ? $request->required : ['email', 'ip', 'url', 'stamp'];
            $requiredFields = array_filter($requiredFields, function($field) {
                return $field !== 'phone'; // Remove 'phone' as it's not a real field
            });
            $requiredFields = array_values($requiredFields); // Re-index array

            $feed = InboundFeed::create([
                'label' => trim($request->label),
                'description' => $request->description ? trim($request->description) : null,
                'idCompany' => (int)$request->idCompany,
                'required' => implode(';', $requiredFields),
                'allowedFields' => implode(';', $request->allowedFields),
                'password' => $this->generateFeedPassword(),
                'dedupeEmail' => ($request->dedupeEmail === '1' || $request->dedupeEmail === true || $request->dedupeEmail === 1) ? '1' : '0',
                'dedupeLandline' => ($request->dedupeLandline === '1' || $request->dedupeLandline === true || $request->dedupeLandline === 1) ? '1' : '0',
                'dedupeCellphone' => ($request->dedupeCellphone === '1' || $request->dedupeCellphone === true || $request->dedupeCellphone === 1) ? '1' : '0',
                'rejectOldLeads' => $request->has('rejectOldLeadsMaxAge') && !empty($request->rejectOldLeadsMaxAge) ? 1 : 0,
                'rejectOldLeadsMaxAge' => $request->rejectOldLeadsMaxAge ? trim($request->rejectOldLeadsMaxAge) : null,
                'dedupeAcross' => $request->dedupeAcross ? trim($request->dedupeAcross) : null,
                'filterTypeUrl' => $request->filterTypeUrl ? trim($request->filterTypeUrl) : null,
                'filterUrl' => $filterUrl,
                'notifications' => ($request->notifications === '1' || $request->notifications === true || $request->notifications === 1) ? '1' : '0',
                'status' => $request->status ?? 'active',
                'chokePercent' => $request->chokePercent ? (int)$request->chokePercent : 0,
                'feedCategory' => $request->feedCategory,
                'dailyLimit' => $request->dailyLimit ? (int)$request->dailyLimit : null,
                'custom1Label' => $request->custom1Label ? trim($request->custom1Label) : null,
                'custom2Label' => $request->custom2Label ? trim($request->custom2Label) : null,
                'custom3Label' => $request->custom3Label ? trim($request->custom3Label) : null,
                'custom4Label' => $request->custom4Label ? trim($request->custom4Label) : null,
                'custom5Label' => $request->custom5Label ? trim($request->custom5Label) : null,
                'custom6Label' => $request->custom6Label ? trim($request->custom6Label) : null,
                'costPerLead' => $request->costPerLead ? (float)$request->costPerLead : 0.00,
                'revenuePerLeadType' => in_array($request->revenuePerLeadType, ['fixed', 'percent'], true) ? $request->revenuePerLeadType : 'fixed',
                'revenuePerLead' => $request->revenuePerLead ? (float)$request->revenuePerLead : 0.00,
                'notifyThresholdCount' => $request->notifyThresholdCount ? (int)$request->notifyThresholdCount : 0,
                'notifyThresholdDays' => $notifyThresholdDays,
                'notifyThresholdTime' => $notifyThresholdTime,
                'salesperson' => $request->salesperson ? (int)$request->salesperson : null,
                'pauseMessage' => $request->pauseMessage ? trim($request->pauseMessage) : null,
                'timezone' => $request->timezone ?? 'America/New_York',
                'timeskew' => $request->timeskew ? trim($request->timeskew) : null,
                'filterState' => $filterState,
                'lookbackPeriod' => $request->lookbackPeriod ? (int)$request->lookbackPeriod : 90,
                'pingTimeout' => $request->pingTimeout ? (int)$request->pingTimeout : 300,
                'requiredPingFields' => $request->has('requiredPingFields') && is_array($request->requiredPingFields) ? implode(';', $request->requiredPingFields) : null,
                'allowedPingFields' => $request->has('allowedPingFields') && is_array($request->allowedPingFields) ? implode(';', $request->allowedPingFields) : null,
                'minimumBirthAge' => $request->minimumBirthAge ? (int)$request->minimumBirthAge : null,
                'maximumBirthAge' => $request->maximumBirthAge ? (int)$request->maximumBirthAge : null,
                'filterZip' => $filterZip,
                'paused' => 0,
            ]);

            return response()->json([
                'status' => 1,
                'error' => 'Successfully added new feed.',
                'data' => [
                    'idFeedIn' => $feed->idFeedIn,
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
                'error' => 'Failed when trying to add a new feed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get API spec for an inbound feed (for display in modal)
     */
    public function getApiSpec($id)
    {
        try {
            $feed = InboundFeed::with('company')->find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }

            $fields = DB::table('fields')
                ->whereIn('fieldType', ['system', 'custom', 'inbound-export'])
                ->whereNotIn('fieldName', ['authorization', 'pswd'])
                ->orderByRaw("REPLACE(fieldName,'c_','')")
                ->get(['fieldName', 'fieldDescription']);

            $required = !empty($feed->required) ? explode(';', $feed->required) : [];
            $allowed = !empty($feed->allowedFields) ? explode(';', $feed->allowedFields) : [];

            // Build API URLs (base from APP_URL / FEEDS_POSTING_URL)
            $postingUrl = config('services.feeds.posting_url');
            $baseUrl = str_starts_with($postingUrl, 'http') ? rtrim($postingUrl, '/') : 'https://' . ltrim($postingUrl, '/');
            $feedUrl = $baseUrl . '/api/live/' . $feed->idFeedIn . '/feed';
            $hash = hash('sha256', $feed->idFeedIn . config('services.feeds.hash_salt') . $feed->password);
            $apiSpecUrl = $baseUrl . '/live/' . $feed->idFeedIn . '/apispec?h=' . urlencode($hash);

            return response()->json([
                'status' => 1,
                'data' => [
                    'company' => $feed->company?->name ?? '',
                    'feedId' => $feed->idFeedIn,
                    'label' => $feed->label,
                    'password' => $feed->password,
                    'required' => $required,
                    'allowedFields' => $allowed,
                    'fields' => $fields,
                    'feedUrl' => $feedUrl,
                    'apiSpecUrl' => $apiSpecUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Ping spec for an inbound feed (ping-specific, includes cost column)
     */
    public function getPingSpec($id)
    {
        try {
            $feed = InboundFeed::with('company')->find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }

            $required = !empty($feed->required) ? explode(';', $feed->required) : [];
            $allowed = !empty($feed->allowedFields) ? explode(';', $feed->allowedFields) : [];
            if (empty($allowed)) {
                $allowed = ['pswd'];
            }
            if (!in_array('pswd', $allowed)) {
                array_unshift($allowed, 'pswd');
            }

            $fieldNames = array_values(array_filter(array_unique(array_map('trim', $allowed))));
            $fieldsFromDb = DB::table('fields')
                ->whereIn('fieldName', $fieldNames)
                ->whereIn('fieldType', ['system', 'custom', 'inbound-export'])
                ->get(['fieldName', 'fieldDefinition', 'fieldFormat', 'fieldDescription']);

            $fieldMap = $fieldsFromDb->keyBy('fieldName');
            $fields = [];
            foreach ($fieldNames as $fn) {
                $f = $fieldMap->get($fn);
                $fields[] = [
                    'fieldName' => $fn === 'pswd' ? 'password' : $fn,
                    'fieldDefinition' => $f->fieldDefinition ?? 'varchar(255)',
                    'fieldFormat' => $f->fieldFormat ?? '',
                    'fieldDescription' => $fn === 'pswd' ? ($feed->password ?? '') : ($f->fieldDescription ?? ''),
                    'required' => in_array($fn, $required) ? 'Yes' : 'No',
                ];
            }

            $postingUrl = config('services.feeds.posting_url');
            $baseUrl = str_starts_with($postingUrl, 'http') ? rtrim($postingUrl, '/') : 'https://' . ltrim($postingUrl, '/');
            $pingUrl = $baseUrl . '/api/live/' . $feed->idFeedIn . '/feed';
            $hash = hash('sha256', $feed->idFeedIn . config('services.feeds.hash_salt') . $feed->password);
            $pingSpecUrl = $baseUrl . '/live/' . $feed->idFeedIn . '/apispec?h=' . urlencode($hash);

            return response()->json([
                'status' => 1,
                'data' => [
                    'company' => $feed->company?->name ?? '',
                    'feedId' => $feed->idFeedIn,
                    'label' => $feed->label,
                    'password' => $feed->password,
                    'costPerLead' => $feed->costPerLead ?? 0,
                    'fields' => $fields,
                    'pingUrl' => $pingUrl,
                    'pingSpecUrl' => $pingSpecUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get filter zip codes from database (for accurate count display)
     */
    public function getFilterZip($id)
    {
        try {
            $feed = InboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }

            $zipCodes = [];
            if (!empty($feed->filterZip)) {
                $data = is_string($feed->filterZip) ? json_decode($feed->filterZip, true) : $feed->filterZip;
                $zipCodes = $data['zipCodes'] ?? [];
            }

            return response()->json([
                'status' => 1,
                'data' => [
                    'zipCodes' => $zipCodes,
                    'count' => count($zipCodes),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Import filter zip codes from CSV file
     */
    public function importFilterZip(Request $request, $id)
    {
        try {
            $feed = InboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }

            $request->validate(['file' => 'required|file|max:10240']);

            $file = $request->file('file');
            $zips = [];
            $handle = fopen($file->getRealPath(), 'r');
            if ($handle) {
                while (($row = fgetcsv($handle)) !== false) {
                    $first = trim($row[0] ?? '');
                    if (preg_match('/^\d{5}$/', $first)) {
                        $zips[] = $first;
                    }
                }
                fclose($handle);
            }

            $zips = array_unique($zips);
            $existing = [];
            if (!empty($feed->filterZip)) {
                $data = is_string($feed->filterZip) ? json_decode($feed->filterZip, true) : $feed->filterZip;
                $existing = $data['zipCodes'] ?? [];
            }
            $merged = array_unique(array_merge($existing, $zips));
            $feed->filterZip = json_encode(['mode' => 'accept', 'zipCodes' => array_values($merged)]);
            $feed->save();

            return response()->json([
                'status' => 1,
                'message' => 'Imported ' . count($zips) . ' zip codes. Total: ' . count($merged),
                'totalZips' => count($merged),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 0, 'error' => $e->errors()[array_key_first($e->errors())][0] ?? 'Validation failed'], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get URL list for a feed (for URL report dropdown)
     */
    public function getUrlList($id)
    {
        try {
            $urls = DB::table('stats_inbound')
                ->where('idFeedIn', $id)
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
     * Get URL report stats (matches legacy getInboundURLStatsReport)
     */
    public function getUrlReport(Request $request, $id)
    {
        try {
            $dateStart = $request->input('dateStart', date('Y-m-d'));
            $dateEnd = $request->input('dateEnd', date('Y-m-d', strtotime('tomorrow')));
            $urlList = $request->input('urlList', []);
            $breakdown = $request->input('breakdown', 'day');
            $sort = $request->input('sort', 'date');
            $group = $request->input('group', 'date');

            $query = DB::table('stats_inbound')->where('idFeedIn', $id);

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
     * Get export columns for a feed
     */
    public function getExportColumns($id)
    {
        try {
            $fields = DB::table('fields')
                ->whereIn('fieldType', ['system', 'custom', 'inbound-export'])
                ->whereNotIn('fieldName', ['authorization', 'pswd'])
                ->orderByRaw("REPLACE(fieldName,'c_','')")
                ->get(['fieldName', 'fieldDescription']);

            return response()->json(['status' => 1, 'data' => $fields]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Create export - streams CSV from data_inbound (matches legacy exportInboundRecords)
     */
    public function createExport(Request $request, $id)
    {
        try {
            $feed = InboundFeed::find($id);
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
            $urlList = $request->input('urlList', []);
            $emailList = $request->input('emailList', []);

            $columnMap = ['stamp' => 'leadstamp'];
            $allowedColumns = ['idRecord', 'idFeedIn', 'timestamp', 'leadstamp', 'listcode', 'url', 'ip', 'email', 'fname', 'lname', 'addr', 'addr2', 'city', 'state', 'zip', 'country', 'dob', 'gender', 'landline', 'cellphone', 'result', 'leadId', 'custom1', 'custom2', 'custom3', 'custom4', 'custom5', 'custom6'];
            $headerColumns = [];
            $selectColumns = [];
            foreach ($columns as $col) {
                $dbCol = $columnMap[$col] ?? $col;
                if (in_array($dbCol, $allowedColumns)) {
                    $headerColumns[] = $col;
                    $selectColumns[] = $dbCol;
                }
            }
            if (empty($selectColumns)) {
                return response()->json(['status' => 0, 'error' => 'No valid columns selected'], 422);
            }

            $query = DB::table('data_inbound')->where('idFeedIn', $id);

            if (!$includeRejects) {
                $query->whereNull('result');
            }
            if (!empty($dateStart)) {
                $query->where('timestamp', '>=', $dateStart . ' 00:00:00');
            }
            if (!empty($dateEnd)) {
                $query->where('timestamp', '<=', $dateEnd . ' 23:59:59');
            }
            if (!empty($urlList) && is_array($urlList)) {
                $query->whereIn('url', $urlList);
            }
            if (!empty($emailList) && is_array($emailList)) {
                $query->where(function ($q) use ($emailList) {
                    foreach ($emailList as $domain) {
                        if (!empty(trim($domain))) {
                            $q->orWhere('email', 'like', '%@' . trim($domain));
                        }
                    }
                });
            }
            $query->orderBy('timestamp', 'desc');
            if (!empty($limit) && (int) $limit > 0) {
                $query->limit((int) $limit);
            }

            $filename = 'inbound_' . $id . '_' . time() . '.csv';

            return response()->streamDownload(function () use ($query, $headerColumns, $selectColumns) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $headerColumns);
                $query->select($selectColumns)->chunk(1000, function ($rows) use ($handle, $selectColumns) {
                    foreach ($rows as $row) {
                        $arr = [];
                        foreach ($selectColumns as $col) {
                            $arr[] = $row->{$col} ?? '';
                        }
                        fputcsv($handle, $arr);
                    }
                });
                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => 'Unable to export: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create import - process uploaded CSV/Excel and insert into data_inbound (matches legacy feedinc import)
     */
    public function createImport(Request $request, $id)
    {
        try {
            $feed = InboundFeed::find($id);
            if (!$feed) {
                return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
            }

            $request->validate(['file' => 'required|file|mimes:csv,xlsx,xls|max:51200']);

            $file = $request->file('file');
            $fieldMapping = [];
            foreach ($request->all() as $key => $val) {
                if (str_starts_with($key, 'field_') && is_numeric($val)) {
                    $fieldMapping[substr($key, 6)] = (int) $val;
                }
            }
            if (empty($fieldMapping)) {
                return response()->json(['status' => 0, 'error' => 'No field mapping provided'], 422);
            }

            $required = is_array($feed->required) ? $feed->required : explode(';', $feed->required ?? '');
            foreach ($required as $r) {
                if (($fieldMapping[$r] ?? null) === null) {
                    return response()->json(['status' => 0, 'error' => "Required field '{$r}' must be mapped"], 422);
                }
            }

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getRealPath());
            $spreadsheet = $reader->load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();

            $imported = 0;
            $invalid = 0;

            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $raw = [];
                foreach ($cellIterator as $cell) {
                    $raw[] = trim((string) $cell->getFormattedValue());
                }

                $data = [];
                foreach ($fieldMapping as $field => $colIdx) {
                    if (isset($raw[$colIdx]) && $raw[$colIdx] !== '') {
                        if ($field === 'stamp') {
                            $data['leadstamp'] = date('Y-m-d H:i:s', strtotime($raw[$colIdx]) ?: time());
                        } elseif ($field !== 'time') {
                            $data[$field] = $raw[$colIdx];
                        }
                    }
                }
                if (isset($data['stamp']) && !isset($data['leadstamp'])) {
                    $data['leadstamp'] = $data['stamp'];
                }

                if (empty($data['leadstamp'])) {
                    $data['leadstamp'] = date('Y-m-d H:i:s');
                }
                if (!empty($data['zip'])) {
                    $data['zip'] = str_pad($data['zip'], 5, '0', STR_PAD_LEFT);
                }

                $statsDay = date('Y-m-d', strtotime($data['leadstamp']));
                $url = substr($data['url'] ?? '', 0, 255) ?: null;

                try {
                    DB::beginTransaction();
                    $idRecord = DB::table('data_inbound')->insertGetId([
                        'idFeedIn' => $id,
                        'timestamp' => now(),
                        'leadstamp' => $data['leadstamp'],
                        'listcode' => substr($data['listcode'] ?? '', 0, 255) ?: null,
                        'url' => $url,
                        'ip' => substr($data['ip'] ?? '', 0, 45) ?: null,
                        'email' => substr($data['email'] ?? '', 0, 255) ?: null,
                        'fname' => substr($data['fname'] ?? '', 0, 50) ?: null,
                        'lname' => substr($data['lname'] ?? '', 0, 50) ?: null,
                        'addr' => substr($data['addr'] ?? '', 0, 150) ?: null,
                        'addr2' => substr($data['addr2'] ?? '', 0, 150) ?: null,
                        'city' => substr($data['city'] ?? '', 0, 75) ?: null,
                        'state' => substr($data['state'] ?? '', 0, 25) ?: null,
                        'zip' => substr($data['zip'] ?? '', 0, 20) ?: null,
                        'country' => substr($data['country'] ?? '', 0, 75) ?: null,
                        'dob' => !empty($data['dob']) ? date('Y-m-d', strtotime($data['dob'])) : null,
                        'gender' => substr($data['gender'] ?? '', 0, 10) ?: null,
                        'landline' => substr($data['landline'] ?? '', 0, 20) ?: null,
                        'cellphone' => substr($data['cellphone'] ?? '', 0, 20) ?: null,
                        'custom1' => substr($data['custom1'] ?? '', 0, 255) ?: null,
                        'custom2' => substr($data['custom2'] ?? '', 0, 255) ?: null,
                        'custom3' => substr($data['custom3'] ?? '', 0, 255) ?: null,
                        'custom4' => substr($data['custom4'] ?? '', 0, 255) ?: null,
                        'custom5' => substr($data['custom5'] ?? '', 0, 255) ?: null,
                        'custom6' => substr($data['custom6'] ?? '', 0, 255) ?: null,
                        'result' => null,
                    ]);

                    DB::statement(
                        'INSERT INTO stats_inbound(idFeedIn,url,stamp,accepted,rejected) VALUES(?,?,?,1,0) ON DUPLICATE KEY UPDATE accepted = accepted + 1',
                        [$id, $url ?? '', $statsDay]
                    );

                    DB::commit();
                    $imported++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $invalid++;
                }
            }

            return response()->json([
                'status' => 1,
                'message' => "Imported {$imported} records." . ($invalid ? " {$invalid} rows skipped (invalid)." : ''),
                'imported' => $imported,
                'invalid' => $invalid,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }
}
