<?php

namespace App\Http\Controllers;

use App\Models\FeedPopulation;
use App\Models\InboundFeed;
use App\Models\OutboundFeed;
use Illuminate\Http\Request;

class FeedPopulationController extends Controller
{
    /**
     * List populations for an outbound feed (matches legacy getPopulations display)
     */
    public function index($idFeedOut)
    {
        try {
            $populations = FeedPopulation::where('idFeedOut', $idFeedOut)
                ->where('isArchived', 0)
                ->with(['inboundFeed' => fn ($q) => $q->with('company:idCompany,name')])
                ->orderByRaw('COALESCE(feedPopulation.`order`, 999) ASC')
                ->orderByDesc('waterfallPriority')
                ->orderByRaw("FIELD(queueType, 'livedata', 'waterfallLimitLive', 'waterfall', 'waterfallLimit', 'queue')")
                ->get();

            $data = $populations->map(function ($p) {
                $item = $p->toArray();
                if ($p->populationType === 'category') {
                    $item['populatingFeed'] = 'Category: ' . ($p->feedCategory ?? '');
                    $item['companyName'] = null;
                    $item['inboundLabel'] = null;
                    $item['inboundDescription'] = null;
                } else {
                    $companyName = $p->inboundFeed?->company?->name ?? '';
                    $label = $p->inboundFeed?->label ?? 'Unknown';
                    $item['companyName'] = $companyName;
                    $item['inboundLabel'] = $label;
                    $item['inboundDescription'] = $p->inboundFeed?->description ?? '';
                    $item['populatingFeed'] = trim($companyName . ' - ' . $label);
                }
                $item['filterTypeUrlDisplay'] = $p->filterTypeUrl === null ? 'Off' : 'On';
                $item['filterUrlDisplay'] = $p->filterTypeUrl === null ? 'Disabled' : ($p->filterTypeUrl === 'accept' ? 'Accepting: ' : 'Rejecting: ') . ($p->filterUrl ? implode(', ', explode(';', $p->filterUrl)) : '');
                $item['filterTypeEmailDisplay'] = $p->filterTypeEmail === null ? 'Off' : 'On';
                $item['filterEmailDisplay'] = $p->filterTypeEmail === null ? 'Disabled' : ($p->filterTypeEmail === 'accept' ? 'Accepting: ' : 'Rejecting: ') . ($p->filterEmail ? implode(', ', explode(';', $p->filterEmail)) : '');
                $item['filterTypeListcodeDisplay'] = $p->filterTypeListcode === null ? 'Off' : 'On';
                $item['filterListcodeDisplay'] = $p->filterTypeListcode === null ? 'Disabled' : ($p->filterTypeListcode === 'accept' ? 'Accepting: ' : 'Rejecting: ') . ($p->filterListcode ? implode(', ', explode(';', $p->filterListcode)) : '');
                $item['forceUrlDisplay'] = $p->forceUrl ? 'On' : 'Off';
                $item['forceUrlListDisplay'] = empty($p->forceUrlList) ? 'No urls assigned for force urls.' : str_replace(';', "\n", $p->forceUrlList);
                return $item;
            });

            return response()->json(['status' => 1, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get inbound feeds for population dropdown
     */
    public function getInboundFeeds(Request $request)
    {
        try {
            $feeds = InboundFeed::where('status', 'active')
                ->orderBy('label')
                ->get(['idFeedIn', 'label', 'feedCategory']);

            return response()->json(['status' => 1, 'data' => $feeds]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get outbound feeds for population dropdown (inbound-centric UI)
     */
    public function getOutboundFeeds(Request $request)
    {
        try {
            $feeds = OutboundFeed::where('status', 'active')
                ->with('company:idCompany,name')
                ->orderBy('label')
                ->get(['idFeedOut', 'label', 'description', 'idCompany', 'feedCategory']);

            $data = $feeds->map(function ($f) {
                return [
                    'idFeedOut' => $f->idFeedOut,
                    'label' => $f->label,
                    'description' => $f->description,
                    'feedCategory' => $f->feedCategory,
                    'companyName' => $f->company?->name ?? '',
                    'displayLabel' => trim(($f->company?->name ?? '') . ' - ' . $f->label),
                ];
            });

            return response()->json(['status' => 1, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * List populations for an inbound feed (outgoing feeds connected to this incoming feed)
     */
    public function indexByInbound($idFeedIn)
    {
        try {
            $populations = FeedPopulation::where('idFeedIn', $idFeedIn)
                ->where('isArchived', 0)
                ->where('populationType', 'individual')
                ->with(['outboundFeed' => fn ($q) => $q->with('company:idCompany,name')])
                ->orderByRaw('COALESCE(feedPopulation.`order`, 999) ASC')
                ->orderByDesc('waterfallPriority')
                ->orderByRaw("FIELD(queueType, 'livedata', 'waterfallLimitLive', 'waterfall', 'waterfallLimit', 'queue')")
                ->get();

            $data = $populations->map(function ($p) {
                $item = $p->toArray();
                $companyName = $p->outboundFeed?->company?->name ?? '';
                $label = $p->outboundFeed?->label ?? 'Unknown';
                $item['companyName'] = $companyName;
                $item['outboundLabel'] = $label;
                $item['outboundDescription'] = $p->outboundFeed?->description ?? '';
                $item['populatingFeed'] = trim($companyName . ' - ' . $label);
                $item['filterTypeUrlDisplay'] = $p->filterTypeUrl === null ? 'Off' : 'On';
                $item['filterUrlDisplay'] = $p->filterTypeUrl === null ? 'Disabled' : ($p->filterTypeUrl === 'accept' ? 'Accepting: ' : 'Rejecting: ') . ($p->filterUrl ? implode(', ', explode(';', $p->filterUrl)) : '');
                $item['filterTypeEmailDisplay'] = $p->filterTypeEmail === null ? 'Off' : 'On';
                $item['filterEmailDisplay'] = $p->filterTypeEmail === null ? 'Disabled' : ($p->filterTypeEmail === 'accept' ? 'Accepting: ' : 'Rejecting: ') . ($p->filterEmail ? implode(', ', explode(';', $p->filterEmail)) : '');
                $item['filterTypeListcodeDisplay'] = $p->filterTypeListcode === null ? 'Off' : 'On';
                $item['filterListcodeDisplay'] = $p->filterTypeListcode === null ? 'Disabled' : ($p->filterTypeListcode === 'accept' ? 'Accepting: ' : 'Rejecting: ') . ($p->filterListcode ? implode(', ', explode(';', $p->filterListcode)) : '');
                $item['forceUrlDisplay'] = $p->forceUrl ? 'On' : 'Off';
                $item['forceUrlListDisplay'] = empty($p->forceUrlList) ? 'No urls assigned for force urls.' : str_replace(';', "\n", $p->forceUrlList);
                return $item;
            });

            return response()->json(['status' => 1, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a population from inbound feed page (connect outgoing feed to this incoming feed)
     */
    public function storeByInbound(Request $request, $idFeedIn)
    {
        try {
            $request->validate([
                'idFeedOut' => 'required|exists:feedout,idFeedOut',
                'order' => 'nullable|integer|min:1|max:999',
                'queueType' => 'required|in:queue,livedata,waterfall,waterfallLimit,waterfallLimitLive',
                'waterfallPriority' => 'nullable|integer|min:0|max:65535',
                'filterTypeUrl' => 'nullable|in:accept,reject',
                'filterTypeEmail' => 'nullable|in:accept,reject',
                'filterTypeListcode' => 'nullable|in:accept,reject',
                'startDate' => 'nullable|date',
            ]);

            $existing = FeedPopulation::where('idFeedIn', $idFeedIn)
                ->where('idFeedOut', $request->idFeedOut)
                ->first();

            if ($existing) {
                if ($existing->isArchived == 0) {
                    return response()->json([
                        'status' => 0,
                        'error' => 'This outgoing feed is already connected to this incoming feed.',
                    ], 422);
                }
                $existing->isArchived = 0;
                $existing->populationType = 'individual';
                $existing->enabled = $request->input('enabled', '1');
                $existing->queueType = $request->queueType;
                $existing->waterfallPriority = $request->input('waterfallPriority', 0);
                $existing->order = $request->input('order', 1);
                $existing->startDate = $request->startDate ?: null;
                $existing->filterTypeUrl = $request->filterTypeUrl ?: null;
                $existing->filterUrl = $request->filterTypeUrl ? ($request->filterUrl ?: null) : null;
                $existing->filterTypeEmail = $request->filterTypeEmail ?: null;
                $existing->filterEmail = $request->filterTypeEmail ? ($request->filterEmail ?: null) : null;
                $existing->filterTypeListcode = $request->filterTypeListcode ?: null;
                $existing->filterListcode = $request->filterTypeListcode ? ($request->filterListcode ?: null) : null;
                $existing->forceUrl = $request->input('forceUrl', 0) ? 1 : 0;
                $existing->forceUrlList = $request->input('forceUrl') ? ($request->forceUrlList ?: null) : null;
                $existing->save();
                $population = $existing;
            } else {
                $population = FeedPopulation::create([
                    'idFeedIn' => $idFeedIn,
                    'idFeedOut' => $request->idFeedOut,
                    'populationType' => 'individual',
                    'enabled' => $request->input('enabled', '1'),
                    'queueType' => $request->queueType,
                    'waterfallPriority' => $request->input('waterfallPriority', 0),
                    'order' => $request->input('order', 1),
                    'startDate' => $request->startDate ?: null,
                    'filterTypeUrl' => $request->filterTypeUrl ?: null,
                    'filterUrl' => $request->filterTypeUrl ? ($request->filterUrl ?: null) : null,
                    'filterTypeEmail' => $request->filterTypeEmail ?: null,
                    'filterEmail' => $request->filterTypeEmail ? ($request->filterEmail ?: null) : null,
                    'filterTypeListcode' => $request->filterTypeListcode ?: null,
                    'filterListcode' => $request->filterTypeListcode ? ($request->filterListcode ?: null) : null,
                    'forceUrl' => $request->input('forceUrl', 0) ? 1 : 0,
                    'forceUrlList' => $request->input('forceUrl') ? ($request->forceUrlList ?: null) : null,
                ]);
            }

            return response()->json([
                'status' => 1,
                'data' => $population,
                'error' => 'Outgoing feed connected successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 0,
                'error' => $e->errors()[array_key_first($e->errors())][0] ?? 'Validation failed',
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get feed categories for population dropdown
     */
    public function getFeedCategories()
    {
        $categories = InboundFeed::where('status', 'active')
            ->distinct()
            ->pluck('feedCategory')
            ->filter()
            ->values();

        return response()->json(['status' => 1, 'data' => $categories]);
    }

    /**
     * Create a new population
     */
    public function store(Request $request, $idFeedOut)
    {
        try {
            $request->validate([
                'populationType' => 'required|in:individual,category',
                'idFeedIn' => 'required_if:populationType,individual',
                'feedCategory' => 'required_if:populationType,category',
                'waterfallPriority' => 'nullable|integer|min:0|max:65535',
                'order' => 'nullable|integer|min:1|max:999',
                'queueType' => 'required|in:queue,livedata,waterfall,waterfallLimit,waterfallLimitLive',
                'filterTypeUrl' => 'nullable|in:accept,reject',
                'filterTypeEmail' => 'nullable|in:accept,reject',
                'filterTypeListcode' => 'nullable|in:accept,reject',
            ]);

            $idFeedIn = $request->populationType === 'individual' ? $request->idFeedIn : null;
            $feedCategory = $request->populationType === 'category' ? $request->feedCategory : null;

            $existing = FeedPopulation::where('idFeedOut', $idFeedOut)
                ->where(function ($q) use ($idFeedIn) {
                    if ($idFeedIn !== null) {
                        $q->where('idFeedIn', $idFeedIn);
                    } else {
                        $q->whereNull('idFeedIn');
                    }
                })
                ->first();

            if ($existing) {
                if ($existing->isArchived == 0) {
                    return response()->json([
                        'status' => 0,
                        'error' => 'This population already exists for this feed.',
                    ], 422);
                }
                // Restore archived population instead of creating (avoids unique constraint violation)
                $existing->isArchived = 0;
                $existing->populationType = $request->populationType;
                $existing->feedCategory = $feedCategory;
                $existing->enabled = $request->input('enabled', '1');
                $existing->queueType = $request->queueType;
                $existing->waterfallPriority = $request->input('waterfallPriority', 0);
                $existing->order = $request->input('order', 1);
                $existing->startDate = $request->startDate ?: null;
                $existing->filterTypeUrl = $request->filterTypeUrl ?: null;
                $existing->filterUrl = $request->filterTypeUrl ? ($request->filterUrl ?: null) : null;
                $existing->filterTypeEmail = $request->filterTypeEmail ?: null;
                $existing->filterEmail = $request->filterTypeEmail ? ($request->filterEmail ?: null) : null;
                $existing->filterTypeListcode = $request->filterTypeListcode ?: null;
                $existing->filterListcode = $request->filterTypeListcode ? ($request->filterListcode ?: null) : null;
                $existing->forceUrl = $request->input('forceUrl', 0) ? 1 : 0;
                $existing->forceUrlList = $request->input('forceUrl') ? ($request->forceUrlList ?: null) : null;
                $existing->save();
                $population = $existing;
            } else {
                $population = FeedPopulation::create([
                'idFeedOut' => $idFeedOut,
                'idFeedIn' => $idFeedIn,
                'feedCategory' => $feedCategory,
                'populationType' => $request->populationType,
                'enabled' => $request->input('enabled', '1'),
                'queueType' => $request->queueType,
                'waterfallPriority' => $request->input('waterfallPriority', 0),
                'order' => $request->input('order', 1),
                'startDate' => $request->startDate ?: null,
                'filterTypeUrl' => $request->filterTypeUrl ?: null,
                'filterUrl' => $request->filterTypeUrl ? ($request->filterUrl ?: null) : null,
                'filterTypeEmail' => $request->filterTypeEmail ?: null,
                'filterEmail' => $request->filterTypeEmail ? ($request->filterEmail ?: null) : null,
                'filterTypeListcode' => $request->filterTypeListcode ?: null,
                'filterListcode' => $request->filterTypeListcode ? ($request->filterListcode ?: null) : null,
                'forceUrl' => $request->input('forceUrl', 0) ? 1 : 0,
                'forceUrlList' => $request->input('forceUrl') ? ($request->forceUrlList ?: null) : null,
            ]);
            }

            return response()->json([
                'status' => 1,
                'data' => $population,
                'error' => 'Population added successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 0,
                'error' => $e->errors()[array_key_first($e->errors())][0] ?? 'Validation failed',
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a population
     */
    public function update(Request $request, $idAssoc)
    {
        try {
            $population = FeedPopulation::find($idAssoc);
            if (!$population) {
                return response()->json(['status' => 0, 'error' => 'Population not found'], 404);
            }

            $request->validate([
                'enabled' => 'nullable|in:0,1',
                'waterfallPriority' => 'nullable|integer|min:0|max:65535',
                'order' => 'nullable|integer|min:1|max:999',
                'queueType' => 'nullable|in:queue,livedata,waterfall,waterfallLimit,waterfallLimitLive',
                'filterTypeUrl' => 'nullable|in:accept,reject',
                'filterTypeEmail' => 'nullable|in:accept,reject',
                'filterTypeListcode' => 'nullable|in:accept,reject',
            ]);

            if ($request->has('enabled')) {
                $population->enabled = $request->enabled;
            }
            if ($request->has('waterfallPriority')) {
                $population->waterfallPriority = $request->waterfallPriority;
            }
            if ($request->has('order')) {
                $population->order = $request->order;
            }
            if ($request->has('queueType')) {
                $population->queueType = $request->queueType;
            }
            if ($request->has('startDate')) {
                $population->startDate = $request->startDate ?: null;
            }
            if (array_key_exists('filterTypeUrl', $request->all())) {
                $population->filterTypeUrl = $request->filterTypeUrl ?: null;
                $population->filterUrl = $request->filterTypeUrl ? ($request->filterUrl ?: null) : null;
            }
            if (array_key_exists('filterTypeEmail', $request->all())) {
                $population->filterTypeEmail = $request->filterTypeEmail ?: null;
                $population->filterEmail = $request->filterTypeEmail ? ($request->filterEmail ?: null) : null;
            }
            if (array_key_exists('filterTypeListcode', $request->all())) {
                $population->filterTypeListcode = $request->filterTypeListcode ?: null;
                $population->filterListcode = $request->filterTypeListcode ? ($request->filterListcode ?: null) : null;
            }
            if (array_key_exists('forceUrl', $request->all())) {
                $population->forceUrl = $request->forceUrl ? 1 : 0;
                $population->forceUrlList = $request->forceUrl ? ($request->forceUrlList ?: null) : null;
            }

            $population->save();

            return response()->json([
                'status' => 1,
                'data' => $population,
                'error' => 'Population updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Toggle population enabled/disabled
     */
    public function toggle($idAssoc)
    {
        try {
            $population = FeedPopulation::find($idAssoc);
            if (!$population) {
                return response()->json(['status' => 0, 'error' => 'Population not found'], 404);
            }

            $population->enabled = $population->enabled === '1' ? '0' : '1';
            $population->save();

            return response()->json([
                'status' => 1,
                'data' => ['enabled' => $population->enabled],
                'error' => 'Population updated.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete (archive) a population
     */
    public function destroy($idAssoc)
    {
        try {
            $population = FeedPopulation::find($idAssoc);
            if (!$population) {
                return response()->json(['status' => 0, 'error' => 'Population not found'], 404);
            }

            $population->isArchived = 1;
            $population->save();

            return response()->json([
                'status' => 1,
                'error' => 'Population removed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'error' => $e->getMessage()], 500);
        }
    }

}
