<?php

namespace App\Services;

use App\Models\FeedPopulation;
use App\Models\InboundFeed;
use App\Models\OutboundFeed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PushIncomingDataService
{
    public const MARKETPLACE_PENDING_MANUAL_REASON = 'Marketplace success received, but price is missing or zero. Pending manual confirmation.';

    /**
     * Push incoming lead data to outgoing feeds based on population settings.
     * Called after a lead is stored in data_inbound.
     *
     * buyerAccepted is true when any outbound path accepts synchronously.
     * Pending, queue-only, rejections, and invalid feed return buyerAccepted false.
     *
     * @param int $idRecord The idRecord from data_inbound
     * @param int $idFeedIn Inbound feed ID
     * @param array $data Request data (url, email, listcode, etc.)
     * @param int|null $idFeedOut If set, force to this specific outbound feed only
     * @return array ['reason' => string|null, 'fields' => array, 'buyerAccepted' => bool, ...]
     */
    public static function pushToOutgoingFeeds(int $idRecord, int $idFeedIn, array $data, ?int $idFeedOut = null): array
    {
        Log::channel('single')->info('[LiveFeed] PushToOutgoingFeeds start', [
            'idRecord' => $idRecord,
            'idFeedIn' => $idFeedIn,
            'idFeedOut' => $idFeedOut,
        ]);

        $inboundFeed = InboundFeed::with('company')->find($idFeedIn);
        if (!$inboundFeed) {
            Log::channel('single')->warning('[LiveFeed] Invalid inbound feed', ['idFeedIn' => $idFeedIn]);
            return ['reason' => 'Invalid feed', 'fields' => [], 'buyerAccepted' => false];
        }

        $data['originalUrl'] = $data['url'] ?? '';

        $liveData = [
            'enabled' => false,
            'accepted' => false,
            'anyProcessed' => false,
            'pendingMarketplace' => false,
            'pingRealtimeFailed' => false,
            'hardRejectByCost' => false,
            'reason' => null,
            'fields' => [],
        ];

        $feedsOut = $idFeedOut
            ? self::getSingleFeedConfig($idFeedOut)
            : self::getPopulationsForInbound($idFeedIn);

        Log::channel('single')->info('[LiveFeed] Populations found', [
            'idFeedIn' => $idFeedIn,
            'count' => count($feedsOut),
        ]);

        if (empty($feedsOut)) {
            Log::channel('single')->info('[LiveFeed] No populations to process - pending');
            return ['status' => 'pending', 'reason' => 'Lead received; no outgoing feed connections.', 'fields' => [], 'buyerAccepted' => false];
        }

        // Phone-preping: run ping/realtime (non-marketplace) before marketplace so pingRealtimeFailed can skip marketplace.
        if (($inboundFeed->feedCategory ?? '') === 'phone-preping' && !$idFeedOut) {
            $feedsOut = self::partitionMarketplaceLast($feedsOut);
        }

        foreach ($feedsOut as $pop) {
            $popIdFeedOut = $pop->idFeedOut ?? null;
            Log::channel('single')->info('[LiveFeed] Processing population', [
                'idFeedOut' => $popIdFeedOut,
                'queueType' => $pop->queueType ?? null,
                'enabled' => $pop->enabled ?? null,
                'delayDump' => $pop->delayDump ?? null,
            ]);

            if ($idFeedOut && $idFeedOut != $popIdFeedOut) {
                Log::channel('single')->info('[LiveFeed] Skip: idFeedOut mismatch');
                continue;
            }

            // if (!$idFeedOut && (empty($pop->enabled) || !empty($pop->delayDump))) {
            if (!$idFeedOut && (empty($pop->enabled))) {
                Log::channel('single')->info('[LiveFeed] Skip: disabled or delayDump', [
                    'enabled' => $pop->enabled ?? null,
                    'delayDump' => $pop->delayDump ?? null,
                ]);
                continue;
            }

            if (!$idFeedOut && !empty($pop->startDate) && $pop->startDate > date('Y-m-d')) {
                Log::channel('single')->info('[LiveFeed] Skip: startDate not reached', ['startDate' => $pop->startDate]);
                continue;
            }

            if (self::outboundRecordExists($idRecord, $idFeedIn, $popIdFeedOut)) {
                Log::channel('single')->info('[LiveFeed] Skip: record already in data_outbound');
                continue;
            }

            if (!self::filterPasses($pop->filterTypeUrl, $data['url'] ?? '', $pop->filterUrl)) {
                Log::channel('single')->info('[LiveFeed] Skip: URL filter failed');
                continue;
            }
            if (!self::filterPasses($pop->filterTypeEmail, $data['email'] ?? '', $pop->filterEmail)) {
                Log::channel('single')->info('[LiveFeed] Skip: email filter failed');
                continue;
            }
            if (!self::filterPasses($pop->filterTypeListcode, $data['listcode'] ?? '', $pop->filterListcode)) {
                Log::channel('single')->info('[LiveFeed] Skip: listcode filter failed');
                continue;
            }

            $feedOutModel = OutboundFeed::find($popIdFeedOut);
            if (!$feedOutModel) {
                Log::channel('single')->warning('[LiveFeed] Skip: outbound feed not found', ['idFeedOut' => $popIdFeedOut]);
                continue;
            }

            // Mark that we have live-type connection: if none accept, lead will be rejected
            $isPingFlow = ($feedOutModel->feedCategory ?? '') === 'phone-preping' && ($inboundFeed->feedCategory ?? '') === 'phone-preping';
            $isLiveType = $isPingFlow || in_array($pop->queueType ?? '', ['livedata', 'waterfall', 'waterfallLimitLive']);
            $isMarketplace = ($feedOutModel->responseType ?? 'realtime') === 'marketplace';
            if ($isLiveType && !$idFeedOut && !$isMarketplace) {
                $liveData['enabled'] = true;
            }

            if (!empty($pop->dailyLimit) && (int) $pop->dailyLimit > 0) {
                $cnt = self::getOutboundDailyCount($popIdFeedOut);
                if ($cnt >= (int) $pop->dailyLimit) {
                    Log::channel('single')->info('[LiveFeed] Skip: daily limit reached', [
                        'dailyLimit' => $pop->dailyLimit,
                        'currentCount' => $cnt,
                    ]);
                    continue;
                }
            }

            $url = $data['url'] ?? null;
            if (!empty($pop->forceUrl) && !empty($pop->forceUrlList)) {
                $url = self::applyUrlRewrite($url, $pop->forceUrlList);
            }

            // Live types: push instantly and return outgoing response. For phone-preping (ping) flows, always use live for instant request-response.
            // Marketplace: add with webhookCallbackId and evaluate synchronous outcome first; webhook can still override later.
            $webhookCallbackId = null;
            $processed = ($isLiveType && !$idFeedOut && !$isMarketplace) ? -1 : 0;
            if ($isMarketplace && !$idFeedOut) {
                $webhookCallbackId = (string) Str::uuid();
                $processed = 0;
            }

            Log::channel('single')->info('[LiveFeed] Adding to data_outbound', [
                'idRecord' => $idRecord,
                'idFeedOut' => $popIdFeedOut,
                'processed' => $processed,
                'queueType' => $pop->queueType ?? null,
                'isMarketplace' => $isMarketplace,
            ]);

            self::addToDataOutbound($idRecord, $idFeedIn, $popIdFeedOut, $url, $processed, $webhookCallbackId);

            if (!$idFeedOut && $isMarketplace) {
                $liveData['pendingMarketplace'] = true;
                if (!self::isWithinProcessingSchedule($pop)) {
                    Log::channel('single')->info('[LiveFeed] Skip marketplace: outside processing schedule');
                    continue;
                }
                if ($pop->queueType === 'waterfall' && $liveData['accepted']) {
                    continue;
                }
                if ($pop->queueType === 'waterfallLimitLive' && $liveData['accepted']) {
                    continue;
                }
                $record = self::getInboundRecord($idRecord);
                if ($record) {
                    $record->url = $url ?? $record->url;
                    Log::channel('single')->info('[LiveFeed] Pushing to marketplace (fire-and-forget)', [
                        'idFeedOut' => $popIdFeedOut,
                        'callbackId' => $webhookCallbackId,
                    ]);
                    self::incrementOutboundSentCount($idRecord, $popIdFeedOut);
                    $result = OutboundPushService::pushRecord($record, $feedOutModel, $inboundFeed, $webhookCallbackId);
                    $buyerResponseRaw = self::buyerRawPayloadFromPushResult($result);
                    $decision = $result['marketplaceDecision'] ?? null;
                    $decision = is_array($decision) ? $decision : null;
                    $final = self::finalizeMarketplaceDecision($decision, $result, $inboundFeed);

                    if ($final['decisionType'] === 'accepted') {
                        self::updateDataOutboundProcessed(
                            $idRecord,
                            $popIdFeedOut,
                            true,
                            $final['reasonText'],
                            $final['price'],
                            false,
                            false,
                            $buyerResponseRaw
                        );
                        $liveData['accepted'] = true;
                        $liveData['anyProcessed'] = true;
                        $liveData['enabled'] = true;
                        $liveData['pendingMarketplace'] = false;
                    } elseif ($final['decisionType'] === 'rejected') {
                        self::updateDataOutboundProcessed(
                            $idRecord,
                            $popIdFeedOut,
                            false,
                            $final['reasonText'],
                            $final['price'],
                            false,
                            false,
                            $buyerResponseRaw
                        );
                        $liveData['accepted'] = false;
                        $liveData['anyProcessed'] = true;
                        $liveData['enabled'] = true;
                        $liveData['reason'] = sprintf('Third-party rejection [Reason: %s] [Code: O%s0]', $final['reasonText'], $popIdFeedOut);
                        $liveData['pendingMarketplace'] = false;
                    } elseif ($final['decisionType'] === 'pending_manual') {
                        $pendingResultText = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        if ($pendingResultText === false || $pendingResultText === '') {
                            $pendingResultText = self::MARKETPLACE_PENDING_MANUAL_REASON;
                        }
                        self::markOutboundPendingManual(
                            $idRecord,
                            $popIdFeedOut,
                            $pendingResultText,
                            $final['price'],
                            $buyerResponseRaw
                        );
                        $liveData['reason'] = self::MARKETPLACE_PENDING_MANUAL_REASON;
                        $liveData['pendingMarketplace'] = true;
                    } else {
                        $liveData['pendingMarketplace'] = true;
                    }
                }
                continue;
            }

            if (!$idFeedOut && $isLiveType) {
                $liveData['enabled'] = true;

                if (!self::isWithinProcessingSchedule($pop)) {
                    Log::channel('single')->info('[LiveFeed] Skip: outside processing schedule');
                    continue;
                }
                if ($pop->queueType === 'waterfall' && $liveData['accepted']) {
                    continue;
                }
                if ($pop->queueType === 'waterfallLimitLive' && $liveData['anyProcessed']) {
                    continue;
                }

                $record = self::getInboundRecord($idRecord);
                if ($record) {
                    $record->url = $url ?? $record->url;
                    Log::channel('single')->info('[LiveFeed] Pushing to outbound URL', [
                        'idFeedOut' => $popIdFeedOut,
                        'postUrl' => $feedOutModel->postUrl ?? '(empty)',
                        'feedType' => $feedOutModel->feedType ?? null,
                    ]);
                    self::incrementOutboundSentCount($idRecord, $popIdFeedOut);
                    $result = OutboundPushService::pushRecord($record, $feedOutModel, $inboundFeed);
                    $buyerResponseRaw = self::buyerRawPayloadFromPushResult($result);

                    $buyerBodyForJson = (string) ($result['responseBody'] ?? $result['text'] ?? '');
                    $jsonFailureReason = OutboundPushService::jsonFailureReasonFromBuyerBody($buyerBodyForJson);
                    if ($jsonFailureReason !== null) {
                        $result['status'] = false;
                        $result['text'] = $jsonFailureReason;
                    } elseif (
                        // For realtime flows, a valid buyer price is mandatory (skip when buyer already reported outcome=failure).
                        !isset($result['cost']) || !is_numeric($result['cost'])
                    ) {
                        if (($result['status'] ?? false)) {
                            $result['status'] = false;
                            $result['text'] = 'price missing in realtime response';
                            $liveData['hardRejectByCost'] = true;
                            $liveData['pendingMarketplace'] = false;
                            Log::channel('single')->info('[LiveFeed] Rejected: missing/invalid price in realtime response', [
                                'idRecord' => $idRecord,
                                'idFeedIn' => $idFeedIn,
                                'idFeedOut' => $popIdFeedOut,
                                'costRaw' => $result['cost'] ?? null,
                                'rawText' => substr((string) ($result['text'] ?? ''), 0, 200),
                            ]);
                        }
                    } else {
                        $buyerPrice = (float) $result['cost'];
                        $maxAllowedPrice = self::computeInboundMaxRealtimePrice($inboundFeed);
                        Log::channel('single')->info('[LiveFeed] Parsed outbound cost for realtime rule', [
                            'idFeedOut' => $popIdFeedOut,
                            'status' => $result['status'] ?? false,
                            'cost' => $buyerPrice,
                            'rawText' => substr((string) ($result['text'] ?? ''), 0, 200),
                            'maxAllowedPrice' => $maxAllowedPrice,
                        ]);
                        if ($maxAllowedPrice !== null && $buyerPrice < $maxAllowedPrice) {
                            $result['status'] = false;
                            $result['text'] = 'cost criteria does not match';
                            $liveData['hardRejectByCost'] = true;
                            $liveData['pendingMarketplace'] = false;
                            Log::channel('single')->info('[LiveFeed] Rejected by inbound CPL+RPL rule', [
                                'idRecord' => $idRecord,
                                'idFeedIn' => $idFeedIn,
                                'idFeedOut' => $popIdFeedOut,
                                'buyerPrice' => $buyerPrice,
                                'maxAllowedPrice' => $maxAllowedPrice,
                                'cpl' => (float) ($inboundFeed->costPerLead ?? 0),
                                'rplType' => (string) ($inboundFeed->revenuePerLeadType ?? 'fixed'),
                                'rplValue' => (float) ($inboundFeed->revenuePerLead ?? 0),
                            ]);
                        }
                    }

                    Log::channel('single')->info('[LiveFeed] Outbound push result', [
                        'idFeedOut' => $popIdFeedOut,
                        'status' => $result['status'] ?? false,
                        'text' => substr($result['text'] ?? '', 0, 200),
                    ]);

                    $liveData['anyProcessed'] = true;
                    if (!($result['status'] ?? false)) {
                        $liveData['reason'] = sprintf(
                            'Third-party rejection [Reason: %s] [Code: O%s0]',
                            self::normalizeOutboundRejectionResult((string) ($result['text'] ?? '')),
                            $popIdFeedOut
                        );
                        if ($isPingFlow) {
                            $liveData['pingRealtimeFailed'] = true;
                        }
                    } else {
                        $liveData['accepted'] = true;
                        $liveData['fields'] = $result['fields'] ?? [];
                    }

                    self::updateDataOutboundProcessed(
                        $idRecord,
                        $popIdFeedOut,
                        $result['status'] ?? false,
                        $result['text'] ?? '',
                        $result['cost'] ?? null,
                        false,
                        false,
                        $buyerResponseRaw
                    );
                    if ($liveData['hardRejectByCost']) {
                        Log::channel('single')->info('[LiveFeed] Stopping further buyer processing due to cost mismatch', [
                            'idRecord' => $idRecord,
                            'idFeedIn' => $idFeedIn,
                            'idFeedOut' => $popIdFeedOut,
                        ]);
                        break;
                    }
                } else {
                    Log::channel('single')->warning('[LiveFeed] Inbound record not found for push', ['idRecord' => $idRecord]);
                }
            }
        }

        if ($liveData['pendingMarketplace'] && !$liveData['accepted']) {
            Log::channel('single')->info('[LiveFeed] Result: pending (marketplace awaiting webhook/manual)');
            $pendingReason = $liveData['reason'];
            // Realtime/ping rows run before marketplace (see partitionMarketplaceLast); don't surface their
            // synchronous rejection text when the final state is marketplace awaiting webhook/manual.
            if (is_string($pendingReason) && str_starts_with($pendingReason, 'Third-party rejection')) {
                $pendingReason = 'Lead received; awaiting buyer response.';
            }

            return [
                'status' => 'pending',
                'reason' => $pendingReason ?: 'Lead received; awaiting buyer response.',
                'fields' => [],
                'buyerAccepted' => false,
            ];
        }

        if ($liveData['enabled']) {
            if (!$liveData['anyProcessed']) {
                Log::channel('single')->info('[LiveFeed] Result: No suitable buyers found');
                return ['reason' => 'No suitable buyers found.', 'fields' => [], 'buyerAccepted' => false];
            }
            if (!$liveData['accepted']) {
                Log::channel('single')->info('[LiveFeed] Result: rejection', ['reason' => $liveData['reason']]);
                return ['reason' => $liveData['reason'], 'fields' => [], 'buyerAccepted' => false];
            }
            Log::channel('single')->info('[LiveFeed] Result: success');
            return ['reason' => null, 'fields' => $liveData['fields'], 'buyerAccepted' => true];
        }

        Log::channel('single')->info('[LiveFeed] Result: no live populations (queue-only)');
        return ['status' => 'queued', 'reason' => null, 'fields' => [], 'buyerAccepted' => false];
    }

    /**
     * Place all marketplace outbound populations after non-marketplace ones (order preserved within each group).
     */
    protected static function partitionMarketplaceLast(array $feedsOut): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($p) => isset($p->idFeedOut) ? (int) $p->idFeedOut : null,
            $feedsOut
        ))));
        if ($ids === []) {
            return $feedsOut;
        }
        $responseTypes = OutboundFeed::whereIn('idFeedOut', $ids)->pluck('responseType', 'idFeedOut');
        $before = [];
        $after = [];
        foreach ($feedsOut as $pop) {
            $id = isset($pop->idFeedOut) ? (int) $pop->idFeedOut : 0;
            $rt = $id ? (string) ($responseTypes[$id] ?? 'realtime') : 'realtime';
            if ($rt === 'marketplace') {
                $after[] = $pop;
            } else {
                $before[] = $pop;
            }
        }
        return array_merge($before, $after);
    }

    protected static function filterPasses(?string $filterType, string $value, ?string $filters): bool
    {
        if ($filterType === null || $filters === null) {
            return true;
        }
        $filterList = array_filter(explode(';', $filters));
        if (empty($filterList)) {
            return true;
        }
        if ($filterType === 'accept') {
            foreach ($filterList as $f) {
                if (stripos($value, $f) !== false) {
                    return true;
                }
            }
            return false;
        }
        if ($filterType === 'reject') {
            foreach ($filterList as $f) {
                if (stripos($value, $f) !== false) {
                    return false;
                }
            }
            return true;
        }
        return true;
    }

    protected static function getPopulationsForInbound(int $idFeedIn): array
    {
        $inbound = InboundFeed::find($idFeedIn);
        $feedCategory = $inbound?->feedCategory ?? null;

        $query = FeedPopulation::query()
            ->leftJoin('feedout', 'feedPopulation.idFeedOut', '=', 'feedout.idFeedOut')
            ->where('feedPopulation.isArchived', 0)
            ->where('feedPopulation.enabled', '1')
            ->where('feedout.status', 'active')
            ->where(function ($q) use ($idFeedIn, $feedCategory) {
                $q->where(function ($q2) use ($idFeedIn) {
                    $q2->where('feedPopulation.populationType', 'individual')
                        ->where('feedPopulation.idFeedIn', $idFeedIn);
                });
                if ($feedCategory) {
                    $q->orWhere(function ($q2) use ($feedCategory) {
                        $q2->where('feedPopulation.populationType', 'category')
                            ->where('feedPopulation.feedCategory', $feedCategory);
                    });
                }
            })
            ->orderByRaw('COALESCE(feedPopulation.`order`, 999) ASC')
            ->orderByDesc('feedPopulation.waterfallPriority')
            ->orderByRaw("FIELD(feedPopulation.queueType, 'livedata', 'waterfallLimitLive', 'waterfall', 'waterfallLimit', 'queue')")
            ->select(
                'feedPopulation.*',
                'feedout.label',
                'feedout.dailyLimit',
                'feedout.delay',
                'feedout.delayDump',
                'feedout.processingSchedule'
            )
            ->get();

        return $query->all();
    }

    protected static function getSingleFeedConfig(int $idFeedOut): array
    {
        $feed = OutboundFeed::find($idFeedOut);
        if (!$feed) {
            return [];
        }
        return [(object) [
            'idFeedOut' => $idFeedOut,
            'enabled' => true,
            'delayDump' => false,
            'startDate' => null,
            'filterTypeUrl' => null,
            'filterTypeEmail' => null,
            'filterTypeListcode' => null,
            'dailyLimit' => $feed->dailyLimit,
            'forceUrl' => null,
            'forceUrlList' => null,
            'queueType' => 'queue',
            'processingSchedule' => $feed->processingSchedule,
        ]];
    }

    protected static function outboundRecordExists(int $idRecord, int $idFeedIn, int $idFeedOut): bool
    {
        return DB::table('data_outbound')
            ->where('idRecord', $idRecord)
            ->where('idFeedIn', $idFeedIn)
            ->where('idFeedOut', $idFeedOut)
            ->exists();
    }

    protected static function applyUrlRewrite(?string $url, string $forceUrlList): ?string
    {
        $mappings = explode(';', $forceUrlList);
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;
        foreach ($mappings as $m) {
            $parts = explode('=', trim($m), 2);
            if (count($parts) === 2 && $host === $parts[0]) {
                return 'http://' . $parts[1];
            }
        }
        return $url;
    }

    protected static function addToDataOutbound(int $idRecord, int $idFeedIn, int $idFeedOut, ?string $url, int $processed = 0, ?string $webhookCallbackId = null): void
    {
        $parsedUrl = $url ? self::parseUrl($url) : null;

        $insertData = [
            'idRecord' => $idRecord,
            'idRecordLegacy' => $idRecord,
            'idFeedIn' => $idFeedIn,
            'idFeedOut' => $idFeedOut,
            'processed' => $processed,
            'url' => $parsedUrl,
        ];
        if ($webhookCallbackId !== null) {
            $insertData['webhookCallbackId'] = $webhookCallbackId;
        }

        try {
            DB::table('data_outbound')->insertOrIgnore($insertData);

            if ($processed !== 1) {
                DB::table('feedout')->where('idFeedOut', $idFeedOut)->increment('queued');
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[LiveFeed] addToDataOutbound failed', [
                'idRecord' => $idRecord,
                'idFeedOut' => $idFeedOut,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected static function getInboundRecord(int $idRecord): ?object
    {
        $row = DB::table('data_inbound')->where('idRecord', $idRecord)->first();
        return $row ? (object) (array) $row : null;
    }

    protected static function incrementOutboundSentCount(int $idRecord, int $idFeedOut): void
    {
        DB::table('data_outbound')
            ->where('idRecord', $idRecord)
            ->where('idFeedOut', $idFeedOut)
            ->increment('sentCount');
    }

    public static function resendSingleOutboundRecord(int $idRecord, int $idFeedOut): array
    {
        $row = DB::table('data_outbound as o')
            ->join('data_inbound as i', function ($join) {
                $join->on('i.idRecord', '=', 'o.idRecord')
                    ->on('i.idFeedIn', '=', 'o.idFeedIn');
            })
            ->where('o.idRecord', $idRecord)
            ->where('o.idFeedOut', $idFeedOut)
            ->select('o.idRecord', 'o.idFeedIn', 'o.idFeedOut', 'o.webhookCallbackId', 'i.*')
            ->first();

        if (!$row) {
            throw new \RuntimeException('Outbound record not found');
        }

        $feedOutModel = OutboundFeed::find($idFeedOut);
        if (!$feedOutModel) {
            throw new \RuntimeException('Outbound feed not found');
        }

        $inboundFeed = InboundFeed::with('company')->find((int) $row->idFeedIn);
        self::incrementOutboundSentCount($idRecord, $idFeedOut);
        $result = OutboundPushService::pushRecord(
            $row,
            $feedOutModel,
            $inboundFeed,
            !empty($row->webhookCallbackId) ? (string) $row->webhookCallbackId : null
        );
        $buyerResponseRaw = self::buyerRawPayloadFromPushResult($result);

        if (($feedOutModel->responseType ?? 'realtime') === 'marketplace') {
            $decision = $result['marketplaceDecision'] ?? null;
            $decision = is_array($decision) ? $decision : null;
            $final = self::finalizeMarketplaceDecision($decision, $result, $inboundFeed);

            if ($final['decisionType'] === 'accepted') {
                self::updateDataOutboundProcessed(
                    $idRecord,
                    $idFeedOut,
                    true,
                    $final['reasonText'],
                    $final['price'],
                    false,
                    true,
                    $buyerResponseRaw
                );
                return ['status' => 1, 'state' => 'accepted'];
            }

            if ($final['decisionType'] === 'rejected') {
                self::updateDataOutboundProcessed(
                    $idRecord,
                    $idFeedOut,
                    false,
                    $final['reasonText'],
                    $final['price'],
                    false,
                    true,
                    $buyerResponseRaw
                );
                return ['status' => 1, 'state' => 'rejected'];
            }

            if ($final['decisionType'] === 'pending_manual') {
                $pendingResultText = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($pendingResultText === false || $pendingResultText === '') {
                    $pendingResultText = self::MARKETPLACE_PENDING_MANUAL_REASON;
                }
                self::markOutboundPendingManual($idRecord, $idFeedOut, $pendingResultText, $final['price'], $buyerResponseRaw);
                return ['status' => 1, 'state' => 'pending_manual'];
            }

            return ['status' => 1, 'state' => 'pending_webhook'];
        }

        self::updateDataOutboundProcessed(
            $idRecord,
            $idFeedOut,
            (bool) ($result['status'] ?? false),
            (string) ($result['text'] ?? ''),
            isset($result['cost']) && is_numeric($result['cost']) ? (float) $result['cost'] : null,
            true,
            true,
            $buyerResponseRaw
        );

        return ['status' => 1, 'state' => ((bool) ($result['status'] ?? false)) ? 'accepted' : 'rejected'];
    }

    /**
     * Process webhook callback from marketplace buyer. Updates data_outbound and stats.
     * Called by OutboundWebhookController.
     */
    public static function processWebhookCallback(int $idRecord, int $idFeedOut, bool $accepted, string $result, ?float $cost = null, ?string $buyerResponseRaw = null): void
    {
        if ($accepted && $cost !== null) {
            $existing = DB::table('data_outbound')
                ->where('idRecord', $idRecord)
                ->where('idFeedOut', $idFeedOut)
                ->first();
            $idFeedIn = (int) ($existing->idFeedIn ?? 0);
            $inboundFeed = $idFeedIn > 0 ? InboundFeed::with('company')->find($idFeedIn) : null;
            $costReject = self::inboundCostCriteriaRejection($cost, $inboundFeed);
            if ($costReject !== null) {
                $accepted = false;
                $result = $costReject;
            }
        }

        self::updateDataOutboundProcessed($idRecord, $idFeedOut, $accepted, $result, $cost, true, true, $buyerResponseRaw);
    }

    public static function confirmMarketplacePending(int $idRecord, int $idFeedOut, ?float $cost = null, ?string $result = null): void
    {
        $existing = DB::table('data_outbound')
            ->where('idRecord', $idRecord)
            ->where('idFeedOut', $idFeedOut)
            ->first();
        $idFeedIn = (int) ($existing->idFeedIn ?? 0);
        $inboundFeed = $idFeedIn > 0 ? InboundFeed::with('company')->find($idFeedIn) : null;

        if ($cost !== null) {
            $costReject = self::inboundCostCriteriaRejection($cost, $inboundFeed);
            if ($costReject !== null) {
                self::updateDataOutboundProcessed(
                    $idRecord,
                    $idFeedOut,
                    false,
                    $costReject,
                    $cost,
                    true,
                    true,
                    null
                );

                return;
            }
        }

        self::updateDataOutboundProcessed(
            $idRecord,
            $idFeedOut,
            true,
            $result ?: 'Marketplace manually confirmed',
            $cost,
            true,
            true,
            null
        );
    }

    /**
     * Retry existing rejected outbound rows for a single feed/date range.
     * Uses Laravel queue workers (no legacy process-jobs.php dependency).
     *
     * @return array{total:int,accepted:int,rejected:int,pending_manual:int,pending_webhook:int,errors:int}
     */
    public static function retryOutboundRejectionsForFeed(int $idFeedOut, string $dateStart, string $dateEnd, int $chunkSize = 200): array
    {
        $summary = [
            'total' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'pending_manual' => 0,
            'pending_webhook' => 0,
            'errors' => 0,
        ];

        $feedOutModel = OutboundFeed::find($idFeedOut);
        if (!$feedOutModel) {
            throw new \RuntimeException('Outbound feed not found');
        }

        $chunkSize = max(1, min(1000, (int) $chunkSize));
        $lastRecordId = 0;
        $inboundCache = [];

        while (true) {
            $rows = DB::table('data_outbound as o')
                ->join('data_inbound as i', function ($join) {
                    $join->on('i.idRecord', '=', 'o.idRecord')
                        ->on('i.idFeedIn', '=', 'o.idFeedIn');
                })
                ->where('o.idFeedOut', $idFeedOut)
                ->where('o.processed', 1)
                ->where('o.accepted', 0)
                ->whereBetween(DB::raw('DATE(o.timestamp)'), [$dateStart, $dateEnd])
                ->where('o.idRecord', '>', $lastRecordId)
                ->orderBy('o.idRecord')
                ->limit($chunkSize)
                ->select(
                    'o.idRecord',
                    'o.idFeedIn',
                    'o.idFeedOut',
                    'o.webhookCallbackId',
                    'i.*'
                )
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastRecordId = max($lastRecordId, (int) $row->idRecord);
                $summary['total']++;

                try {
                    $idFeedIn = (int) $row->idFeedIn;
                    if (!array_key_exists($idFeedIn, $inboundCache)) {
                        $inboundCache[$idFeedIn] = InboundFeed::with('company')->find($idFeedIn);
                    }
                    $inboundFeed = $inboundCache[$idFeedIn];

                    self::incrementOutboundSentCount((int) $row->idRecord, $idFeedOut);
                    $result = OutboundPushService::pushRecord(
                        $row,
                        $feedOutModel,
                        $inboundFeed,
                        !empty($row->webhookCallbackId) ? (string) $row->webhookCallbackId : null
                    );
                    $buyerResponseRaw = self::buyerRawPayloadFromPushResult($result);

                    if (($feedOutModel->responseType ?? 'realtime') === 'marketplace') {
                        $decision = $result['marketplaceDecision'] ?? null;
                        $decision = is_array($decision) ? $decision : null;
                        $final = self::finalizeMarketplaceDecision($decision, $result, $inboundFeed);

                        if ($final['decisionType'] === 'accepted') {
                            self::updateDataOutboundProcessed(
                                (int) $row->idRecord,
                                $idFeedOut,
                                true,
                                $final['reasonText'],
                                $final['price'],
                                true,
                                true,
                                $buyerResponseRaw
                            );
                            $summary['accepted']++;
                        } elseif ($final['decisionType'] === 'rejected') {
                            self::updateDataOutboundProcessed(
                                (int) $row->idRecord,
                                $idFeedOut,
                                false,
                                $final['reasonText'],
                                $final['price'],
                                true,
                                true,
                                $buyerResponseRaw
                            );
                            $summary['rejected']++;
                        } elseif ($final['decisionType'] === 'pending_manual') {
                            $pendingResultText = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                            if ($pendingResultText === false || $pendingResultText === '') {
                                $pendingResultText = self::MARKETPLACE_PENDING_MANUAL_REASON;
                            }
                            DB::table('data_outbound')
                                ->where('idRecord', (int) $row->idRecord)
                                ->where('idFeedOut', $idFeedOut)
                                ->update([
                                    'result' => $pendingResultText,
                                    'cost' => $final['price'],
                                    'buyer_response_raw' => $buyerResponseRaw,
                                ]);
                            $summary['pending_manual']++;
                        } else {
                            $summary['pending_webhook']++;
                        }
                    } else {
                        self::updateDataOutboundProcessed(
                            (int) $row->idRecord,
                            $idFeedOut,
                            (bool) ($result['status'] ?? false),
                            (string) ($result['text'] ?? ''),
                            isset($result['cost']) && is_numeric($result['cost']) ? (float) $result['cost'] : null,
                            true,
                            true,
                            $buyerResponseRaw
                        );

                        if ((bool) ($result['status'] ?? false)) {
                            $summary['accepted']++;
                        } else {
                            $summary['rejected']++;
                        }
                    }
                } catch (\Throwable $e) {
                    $summary['errors']++;
                    Log::channel('single')->error('[LiveFeed] RetryOutboundRejections error', [
                        'idRecord' => $row->idRecord ?? null,
                        'idFeedOut' => $idFeedOut,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $summary;
    }

    /**
     * Resend existing pending marketplace rows for a single outbound feed.
     * This never inserts new inbound/outbound rows; it only updates current pending rows.
     *
     * @return array{total:int,accepted:int,rejected:int,pending_manual:int,pending_webhook:int,errors:int}
     */
    public static function resendPendingMarketplaceForFeed(int $idFeedOut, int $chunkSize = 200): array
    {
        $summary = [
            'total' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'pending_manual' => 0,
            'pending_webhook' => 0,
            'errors' => 0,
        ];

        $feedOutModel = OutboundFeed::find($idFeedOut);
        if (!$feedOutModel) {
            throw new \RuntimeException('Outbound feed not found');
        }
        if (($feedOutModel->responseType ?? 'realtime') !== 'marketplace') {
            throw new \RuntimeException('Resend pending is allowed only for marketplace feeds');
        }

        $chunkSize = max(1, min(1000, (int) $chunkSize));
        $lastRecordId = 0;
        $inboundCache = [];

        while (true) {
            $rows = DB::table('data_outbound as o')
                ->join('data_inbound as i', function ($join) {
                    $join->on('i.idRecord', '=', 'o.idRecord')
                        ->on('i.idFeedIn', '=', 'o.idFeedIn');
                })
                ->where('o.idFeedOut', $idFeedOut)
                ->where('o.processed', 0)
                ->where('o.idRecord', '>', $lastRecordId)
                ->orderBy('o.idRecord')
                ->limit($chunkSize)
                ->select(
                    'o.idRecord',
                    'o.idFeedIn',
                    'o.idFeedOut',
                    'o.webhookCallbackId',
                    'i.*'
                )
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastRecordId = max($lastRecordId, (int) $row->idRecord);
                $summary['total']++;

                try {
                    $idFeedIn = (int) $row->idFeedIn;
                    if (!array_key_exists($idFeedIn, $inboundCache)) {
                        $inboundCache[$idFeedIn] = InboundFeed::with('company')->find($idFeedIn);
                    }
                    $inboundFeed = $inboundCache[$idFeedIn];

                    self::incrementOutboundSentCount((int) $row->idRecord, $idFeedOut);
                    $result = OutboundPushService::pushRecord(
                        $row,
                        $feedOutModel,
                        $inboundFeed,
                        !empty($row->webhookCallbackId) ? (string) $row->webhookCallbackId : null
                    );
                    $buyerResponseRaw = self::buyerRawPayloadFromPushResult($result);

                    $decision = $result['marketplaceDecision'] ?? null;
                    $decision = is_array($decision) ? $decision : null;
                    $final = self::finalizeMarketplaceDecision($decision, $result, $inboundFeed);

                    if ($final['decisionType'] === 'accepted') {
                        self::updateDataOutboundProcessed(
                            (int) $row->idRecord,
                            $idFeedOut,
                            true,
                            $final['reasonText'],
                            $final['price'],
                            false,
                            true,
                            $buyerResponseRaw
                        );
                        $summary['accepted']++;
                    } elseif ($final['decisionType'] === 'rejected') {
                        self::updateDataOutboundProcessed(
                            (int) $row->idRecord,
                            $idFeedOut,
                            false,
                            $final['reasonText'],
                            $final['price'],
                            false,
                            true,
                            $buyerResponseRaw
                        );
                        $summary['rejected']++;
                    } elseif ($final['decisionType'] === 'pending_manual') {
                        $pendingResultText = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        if ($pendingResultText === false || $pendingResultText === '') {
                            $pendingResultText = self::MARKETPLACE_PENDING_MANUAL_REASON;
                        }
                        self::markOutboundPendingManual(
                            (int) $row->idRecord,
                            $idFeedOut,
                            $pendingResultText,
                            $final['price'],
                            $buyerResponseRaw
                        );
                        $summary['pending_manual']++;
                    } else {
                        $summary['pending_webhook']++;
                    }
                } catch (\Throwable $e) {
                    $summary['errors']++;
                    Log::channel('single')->error('[LiveFeed] ResendPendingMarketplace error', [
                        'idRecord' => $row->idRecord ?? null,
                        'idFeedOut' => $idFeedOut,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $summary;
    }

    /**
     * Persistable buyer-side payload: prefer explicit responseBody from OutboundPushService, then legacy "text",
     * then HTTP status when the body was empty (realtime rules still replace result text afterwards).
     */
    protected static function buyerRawPayloadFromPushResult(array $pushResult): string
    {
        $body = array_key_exists('responseBody', $pushResult)
            ? (string) $pushResult['responseBody']
            : '';
        if ($body === '') {
            $body = (string) ($pushResult['text'] ?? '');
        }
        $code = array_key_exists('httpStatusCode', $pushResult) ? (int) $pushResult['httpStatusCode'] : 0;

        if ($body !== '') {
            return $body;
        }
        if ($code > 0) {
            return sprintf('[HTTP %d] Empty response body', $code);
        }

        return '[OutboundPush] No buyer HTTP body was captured';
    }

    protected static function updateDataOutboundProcessed(int $idRecord, int $idFeedOut, bool $accepted, string $result, ?float $cost = null, bool $allowOverride = false, bool $syncInbound = false, ?string $buyerResponseRaw = null): void
    {
        $existing = DB::table('data_outbound')
            ->where('idRecord', $idRecord)
            ->where('idFeedOut', $idFeedOut)
            ->first();
        if (!$existing) {
            return;
        }
        if (!$allowOverride && (int) ($existing->processed ?? 0) === 1) {
            return;
        }

        if (!$accepted) {
            $result = self::normalizeOutboundRejectionResult($result);
        }

        $previousAccepted = (int) ($existing->accepted ?? 0) === 1;
        $wasProcessed = (int) ($existing->processed ?? 0) === 1;
        $update = [
            'timestamp' => now(),
            'processed' => 1,
            'accepted' => $accepted ? 1 : 0,
            'isBillable' => $accepted ? 1 : 0,
            'result' => $result,
        ];
        if ($cost !== null) {
            $update['cost'] = $cost;
        }
        if ($buyerResponseRaw !== null) {
            $update['buyer_response_raw'] = $buyerResponseRaw;
        }
        DB::table('data_outbound')
            ->where('idRecord', $idRecord)
            ->where('idFeedOut', $idFeedOut)
            ->update($update);

        $row = DB::table('data_outbound')->where('idRecord', $idRecord)->where('idFeedOut', $idFeedOut)->first();
        $url = $row->url ?? ($existing->url ?? '');
        $stamp = date('Y-m-d');

        if ($wasProcessed && $previousAccepted !== $accepted) {
            if ($previousAccepted) {
                DB::statement(
                    'INSERT INTO stats_outbound (idFeedOut, url, stamp, accepted, billable) VALUES (?, ?, ?, 0, 0) ON DUPLICATE KEY UPDATE accepted = GREATEST(0, accepted - 1), billable = GREATEST(0, billable - 1)',
                    [$idFeedOut, $url, $stamp]
                );
            } else {
                DB::statement(
                    'INSERT INTO stats_outbound (idFeedOut, url, stamp, rejected) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE rejected = GREATEST(0, rejected - 1)',
                    [$idFeedOut, $url, $stamp]
                );
            }
        }

        if (!$wasProcessed || $previousAccepted !== $accepted) {
            if ($accepted) {
                DB::statement(
                    'INSERT INTO stats_outbound (idFeedOut, url, stamp, accepted, billable) VALUES (?, ?, ?, 1, 1) ON DUPLICATE KEY UPDATE accepted = accepted + 1, billable = billable + 1',
                    [$idFeedOut, $url, $stamp]
                );
            } else {
                DB::statement(
                    'INSERT INTO stats_outbound (idFeedOut, url, stamp, rejected) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE rejected = rejected + 1',
                    [$idFeedOut, $url, $stamp]
                );
            }
        }

        if (!$wasProcessed) {
            DB::table('feedout')->where('idFeedOut', $idFeedOut)->decrement('queued');
        }

        if ($syncInbound) {
            self::syncInboundResultAndStats(
                $idRecord,
                (int) ($existing->idFeedIn ?? 0),
                $accepted ? 'Success' : sprintf('Third-party rejection [Reason: %s] [Code: O%d0]', $result, $idFeedOut)
            );
        }
    }

    protected static function markOutboundPendingManual(int $idRecord, int $idFeedOut, string $result, ?float $cost = null, ?string $buyerResponseRaw = null): void
    {
        $update = [
            'result' => $result,
        ];
        if ($cost !== null) {
            $update['cost'] = $cost;
        }
        if ($buyerResponseRaw !== null) {
            $update['buyer_response_raw'] = $buyerResponseRaw;
        }
        DB::table('data_outbound')
            ->where('idRecord', $idRecord)
            ->where('idFeedOut', $idFeedOut)
            ->where('processed', 0)
            ->update($update);
    }

    protected static function syncInboundResultAndStats(int $idRecord, int $idFeedIn, string $newResult): void
    {
        if ($idFeedIn <= 0) {
            return;
        }
        $inbound = DB::table('data_inbound')->where('idRecord', $idRecord)->first();
        if (!$inbound) {
            return;
        }
        $previousResult = (string) ($inbound->result ?? '');
        $from = self::inboundResultState($previousResult);
        $to = self::inboundResultState($newResult);
        DB::table('data_inbound')->where('idRecord', $idRecord)->update(['result' => $newResult]);

        if ($from === $to) {
            return;
        }
        $stamp = date('Y-m-d', strtotime((string) ($inbound->leadstamp ?? now()->toDateString())));
        $url = self::parseUrl((string) ($inbound->url ?? ''));

        $decrementExpr = match ($from) {
            'accepted' => 'accepted = GREATEST(0, accepted - 1)',
            'rejected' => 'rejected = GREATEST(0, rejected - 1)',
            default => 'pending = GREATEST(0, COALESCE(pending, 0) - 1)',
        };
        $incrementExpr = match ($to) {
            'accepted' => 'accepted = accepted + 1',
            'rejected' => 'rejected = rejected + 1',
            default => 'pending = COALESCE(pending, 0) + 1',
        };

        DB::statement(
            "INSERT INTO stats_inbound (idFeedIn, url, stamp, accepted, rejected, pending) VALUES (?, ?, ?, 0, 0, 0) ON DUPLICATE KEY UPDATE {$decrementExpr}, {$incrementExpr}",
            [$idFeedIn, $url, $stamp]
        );
    }

    protected static function inboundResultState(string $result): string
    {
        if ($result === '' || $result === 'Success') {
            return 'accepted';
        }
        if ($result === 'Pending') {
            return 'pending';
        }
        return 'rejected';
    }

    protected static function getOutboundDailyCount(int $idFeedOut): int
    {
        $row = DB::table('stats_outbound')
            ->where('idFeedOut', $idFeedOut)
            ->where('stamp', date('Y-m-d'))
            ->selectRaw('COALESCE(SUM(accepted), 0) as cnt')
            ->first();
        return (int) ($row->cnt ?? 0);
    }

    protected static function isWithinProcessingSchedule(object $pop): bool
    {
        $schedule = $pop->processingSchedule ?? null;
        if (empty($schedule)) {
            return true;
        }
        $schedule = is_string($schedule) ? json_decode($schedule) : $schedule;
        if (!$schedule) {
            return true;
        }
        $day = strtolower(date('D'));
        $dayKey = substr($day, 0, 3);
        $dayData = $schedule->{$dayKey} ?? null;
        if (empty($dayData->enabled ?? false)) {
            return false;
        }
        $now = date('H:i:s');
        if (!empty($dayData->startTime) && $now < $dayData->startTime) {
            return false;
        }
        if (!empty($dayData->endTime) && $now > $dayData->endTime) {
            return false;
        }
        return true;
    }

    /**
     * Compute max acceptable realtime buyer price from inbound CPL + RPL.
     * - fixed:   CPL + RPL
     * - percent: CPL + (CPL * RPL / 100)
     */
    protected static function computeInboundMaxRealtimePrice(InboundFeed $inboundFeed): ?float
    {
        $cpl = isset($inboundFeed->costPerLead) && is_numeric($inboundFeed->costPerLead)
            ? (float) $inboundFeed->costPerLead
            : 0.0;
        $rpl = isset($inboundFeed->revenuePerLead) && is_numeric($inboundFeed->revenuePerLead)
            ? (float) $inboundFeed->revenuePerLead
            : 0.0;
        $rplType = (string) ($inboundFeed->revenuePerLeadType ?? 'fixed');

        if ($rplType === 'percent') {
            return $cpl + (($cpl * $rpl) / 100.0);
        }

        return $cpl + $rpl;
    }

    /**
     * Best-effort buyer price from marketplace decision JSON or raw push extract (costKey / top-level price).
     */
    protected static function marketplaceEffectiveBuyerPrice(?array $decision, array $pushResult): ?float
    {
        if (is_array($decision) && isset($decision['price']) && is_numeric($decision['price'])) {
            return (float) $decision['price'];
        }
        if (isset($pushResult['cost']) && is_numeric($pushResult['cost'])) {
            return (float) $pushResult['cost'];
        }

        return null;
    }

    /**
     * Same CPL+RPL floor as realtime: reject when buyer price is strictly below inbound max.
     */
    protected static function inboundCostCriteriaRejection(?float $buyerPrice, ?InboundFeed $inboundFeed): ?string
    {
        if ($buyerPrice === null || !$inboundFeed) {
            return null;
        }
        $maxAllowedPrice = self::computeInboundMaxRealtimePrice($inboundFeed);
        if ($maxAllowedPrice !== null && $buyerPrice < $maxAllowedPrice) {
            return 'cost criteria does not match';
        }

        return null;
    }

    protected static function normalizeOutboundRejectionResult(string $result): string
    {
        $t = trim($result);

        return $t !== '' ? $t : 'Unknown';
    }

    /**
     * Apply inbound economics to marketplace parser output so realtime and marketplace agree on CPL+RPL.
     * Keeps pending_manual (success without usable price) untouched so those leads stay pending until price exists.
     *
     * @return array{decisionType:string, reasonText:string, price:?float}
     */
    protected static function finalizeMarketplaceDecision(?array $decision, array $pushResult, ?InboundFeed $inboundFeed): array
    {
        $decisionArr = is_array($decision) ? $decision : [];
        $decisionType = $decisionArr['decisionType'] ?? 'pending_webhook';
        $priceFromDecision = isset($decisionArr['price']) && is_numeric($decisionArr['price']) ? (float) $decisionArr['price'] : null;
        $effectivePrice = self::marketplaceEffectiveBuyerPrice(is_array($decision) ? $decision : null, $pushResult);
        $costRejectReason = self::inboundCostCriteriaRejection($effectivePrice, $inboundFeed);

        // Do not replace an explicit buyer rejection (e.g. outcome=failure + reason) with a cost message when price is 0/low.
        if ($costRejectReason !== null && $decisionType !== 'pending_manual' && $decisionType !== 'rejected') {
            Log::channel('single')->info('[LiveFeed] Marketplace outcome overridden by inbound CPL+RPL rule', [
                'priorDecisionType' => $decisionType,
                'effectivePrice' => $effectivePrice,
            ]);

            return [
                'decisionType' => 'rejected',
                'reasonText' => self::normalizeOutboundRejectionResult($costRejectReason),
                'price' => $priceFromDecision ?? $effectivePrice,
            ];
        }

        if ($decisionType === 'accepted') {
            return [
                'decisionType' => 'accepted',
                'reasonText' => (string) ($decisionArr['reason'] ?? 'Marketplace accepted'),
                'price' => $priceFromDecision,
            ];
        }

        if ($decisionType === 'rejected') {
            $base = trim((string) ($decisionArr['reason'] ?? ''));

            return [
                'decisionType' => 'rejected',
                'reasonText' => self::normalizeOutboundRejectionResult($base),
                'price' => $priceFromDecision ?? $effectivePrice,
            ];
        }

        if ($decisionType === 'pending_manual') {
            return [
                'decisionType' => 'pending_manual',
                'reasonText' => '',
                'price' => $priceFromDecision,
            ];
        }

        // Buyer returned HTTP success and a usable price, but JSON lacked outcome success/failure — accept when CPL+RPL passes.
        if ($decisionType === 'pending_webhook'
            && ($pushResult['status'] ?? false)
            && $effectivePrice !== null
            && $effectivePrice > 0
            && self::inboundCostCriteriaRejection($effectivePrice, $inboundFeed) === null
        ) {
            return [
                'decisionType' => 'accepted',
                'reasonText' => 'Marketplace accepted',
                'price' => $effectivePrice,
            ];
        }

        return [
            'decisionType' => 'pending_webhook',
            'reasonText' => '',
            'price' => $priceFromDecision ?? $effectivePrice,
        ];
    }

    protected static function parseUrl(?string $url): string
    {
        if (empty($url)) {
            return '';
        }
        $url = strtolower($url);
        if (!str_contains($url, 'http')) {
            $url = 'http://' . $url;
        }
        $host = parse_url($url, PHP_URL_HOST);
        return $host ? str_replace('www.', '', $host) : '';
    }
}
