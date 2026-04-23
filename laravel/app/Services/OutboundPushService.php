<?php

namespace App\Services;

use App\Models\InboundFeed;
use App\Models\OutboundFeed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OutboundPushService
{
    /**
     * Normalize feed JSON-backed fields to arrays even if legacy rows are double-encoded.
     */
    protected static function normalizeArrayField(mixed $value, array $fallback = []): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return $fallback;
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Handle legacy double-encoded JSON payloads.
        if (is_string($decoded)) {
            $decodedNested = json_decode($decoded, true);
            if (is_array($decodedNested)) {
                return $decodedNested;
            }
        }

        return $fallback;
    }

    /**
     * Build request data from inbound record and feed config, then send to outbound URL.
     * Returns ['status' => bool, 'text' => string, 'fields' => array]
     * @param string|null $webhookCallbackId When provided (marketplace), inject as callbackId for webhook return
     */
    public static function pushRecord(object $record, OutboundFeed $feed, ?InboundFeed $inboundFeed = null, ?string $webhookCallbackId = null): array
    {
        $staticFields = self::normalizeArrayField($feed->staticFieldsJSON, []);
        $varFields = self::normalizeArrayField($feed->varFieldsJSON, []);
        $valueMap = self::normalizeArrayField($feed->valueMap, []);

        $row = (object) array_merge((array) $record, [
            'stamp' => $record->stamp ?? $record->leadstamp ?? now()->format('Y-m-d H:i:s'),
        ]);

        if (!empty($record->customFields)) {
            $customFields = is_string($record->customFields) ? json_decode($record->customFields, true) : $record->customFields;
            if (is_array($customFields)) {
                foreach ($customFields as $key => $val) {
                    $row->{$key} = $val;
                }
            }
        }

        if (!empty($valueMap)) {
            foreach ($valueMap as $vm) {
                if (isset($vm['field'], $vm['oldValue'], $vm['newValue']) && isset($row->{$vm['field']}) && $row->{$vm['field']} == $vm['oldValue']) {
                    $row->{$vm['field']} = $vm['newValue'];
                }
            }
        }

        $requestData = [];
        foreach ($staticFields as $key => $val) {
            $requestData[$key] = $val;
        }

        $timezone = $feed->timezone ?? 'UTC';
        $stampDate = null;
        if (!empty($row->stamp)) {
            try {
                $stampDate = new \DateTime($row->stamp, new \DateTimeZone('UTC'));
                $stampDate->setTimezone(new \DateTimeZone($timezone));
            } catch (\Exception $e) {
                $stampDate = new \DateTime('now', new \DateTimeZone($timezone));
            }
        } else {
            $stampDate = new \DateTime('now', new \DateTimeZone($timezone));
        }

        $genderMap = ['M' => 'Male', 'F' => 'Female'];

        foreach ($varFields as $externalKey => $internalField) {
            $mapVal = is_array($internalField) ? ($internalField['map'] ?? $internalField['field'] ?? '') : $internalField;
            $value = '';

            switch ($mapVal) {
                case 'recordId':
                    $value = $row->idRecord ?? '';
                    break;
                case 'urlAssign':
                    $urlassignments = explode(';', $feed->urlassignments ?? '');
                    foreach ($urlassignments as $instructions) {
                        if (!empty($instructions)) {
                            $parts = explode('=', $instructions, 2);
                            if (count($parts) === 2 && stripos($row->url ?? '', $parts[0]) !== false) {
                                $value = $parts[1];
                                break;
                            }
                        }
                    }
                    break;
                case 'dobUS':
                    $value = !empty($row->dob) ? date('m-d-Y', strtotime($row->dob)) : '';
                    break;
                case 'dob_slashes':
                    $value = !empty($row->dob) ? date('m/d/Y', strtotime($row->dob)) : '';
                    break;
                case 'gender_full':
                    $value = $genderMap[$row->gender ?? ''] ?? ($row->gender ?? '');
                    break;
                case 'stampUS':
                    $value = $stampDate ? $stampDate->format('m-d-Y H:i:s') : '';
                    break;
                case 'stampUS_dateOnly':
                    $value = $stampDate ? $stampDate->format('m-d-Y') : '';
                    break;
                case 'stamp_YYYY-mm-dd':
                    $value = $stampDate ? $stampDate->format('Y-m-d') : '';
                    break;
                case 'cellphone_prepend1':
                    $value = !empty($row->cellphone) ? '1' . $row->cellphone : '';
                    break;
                case 'landline_prepend1':
                    $value = !empty($row->landline) ? '1' . $row->landline : '';
                    break;
                case 'inbound_company':
                    $value = $inboundFeed?->company?->name ?? '';
                    break;
                case 'inbound_label':
                    $value = $inboundFeed?->label ?? '';
                    break;
                case 'inbound_cpl':
                    $value = $inboundFeed?->costPerLead ?? '';
                    break;
                case 'callbackId':
                    $value = $webhookCallbackId ?? '';
                    break;
                default:
                    $value = $row->{$mapVal} ?? $row->{$externalKey} ?? '';
                    break;
            }

            $requestData[$externalKey] = $value;
        }

        if (!empty($valueMap)) {
            foreach ($valueMap as $vm) {
                if (isset($vm['field'], $vm['oldValue'], $vm['newValue']) && isset($requestData[$vm['field']]) && $requestData[$vm['field']] == $vm['oldValue']) {
                    $requestData[$vm['field']] = $vm['newValue'];
                }
            }
        }

        // Marketplace: inject leadId/callbackId so buyer can return it in webhook body
        if ($webhookCallbackId !== null) {
            $requestData['leadId'] = $webhookCallbackId;
            $requestData['callbackId'] = $webhookCallbackId;
        }

        $url = $feed->postUrl;
        if (empty($url)) {
            Log::channel('single')->warning('[LiveFeed] OutboundPush: No post URL configured', [
                'idFeedOut' => $feed->idFeedOut ?? null,
            ]);
            return ['status' => false, 'text' => 'No post URL configured', 'fields' => []];
        }

        $prepingResult = self::runPrepingIfEnabled($feed, $requestData);
        if (!$prepingResult['ok']) {
            Log::channel('single')->info('[LiveFeed] OutboundPush: Preping failed', [
                'idFeedOut' => $feed->idFeedOut ?? null,
                'text' => substr($prepingResult['text'], 0, 300),
            ]);
            return [
                'status' => false,
                'text' => $prepingResult['text'],
                'fields' => [],
                'failureType' => 'preping_failed',
            ];
        }

        try {
            Log::channel('single')->info('[LiveFeed] OutboundPush: Sending request', [
                'idFeedOut' => $feed->idFeedOut ?? null,
                'feedType' => $feed->feedType ?? null,
                'url' => $url,
            ]);
            $response = self::sendRequest($feed->feedType, $url, $requestData, []);
            $success = self::checkSuccess($feed, $response['body'], $response['statusCode']);

            // For ping feeds with costKey: parse cost from response, apply 124% rule
            $outboundCost = null;
            $costKey = $feed->costKey ?? null;
            if (!empty($costKey)) {
                $outboundCost = self::extractCostFromResponse($response['body'], $costKey);
                $inboundCost = isset($record->cost) && is_numeric($record->cost) ? (float) $record->cost : null;
                if ($outboundCost !== null && $inboundCost !== null && $inboundCost > 0) {
                    $minRequired = $inboundCost * 1.24;
                    if ($outboundCost < $minRequired) {
                        $success = false;
                        Log::channel('single')->info('[LiveFeed] OutboundPush: Rejected by 124% cost rule', [
                            'idFeedOut' => $feed->idFeedOut ?? null,
                            'outboundCost' => $outboundCost,
                            'inboundCost' => $inboundCost,
                            'minRequired' => $minRequired,
                        ]);
                    }
                }
            }

            Log::channel('single')->info('[LiveFeed] OutboundPush: Response received', [
                'idFeedOut' => $feed->idFeedOut ?? null,
                'statusCode' => $response['statusCode'] ?? null,
                'success' => $success,
                'bodyPreview' => substr($response['body'] ?? '', 0, 300),
            ]);
            $marketplaceDecision = null;
            if (($feed->responseType ?? 'realtime') === 'marketplace') {
                $marketplaceDecision = self::parseMarketplaceDecision(
                    (string) ($response['body'] ?? ''),
                    (int) ($response['statusCode'] ?? 0)
                );
            }
            return [
                'status' => $success,
                'text' => $response['body'],
                'fields' => [],
                'cost' => $outboundCost,
                'marketplaceDecision' => $marketplaceDecision,
            ];
        } catch (\Exception $e) {
            Log::channel('single')->error('[LiveFeed] OutboundPush: Exception', [
                'idFeedOut' => $feed->idFeedOut ?? null,
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['status' => false, 'text' => $e->getMessage(), 'fields' => []];
        }
    }

    /**
     * When preping is enabled and prepingUrl is set, call preping first. Success = 2xx and JSON {"result":"true"}.
     *
     * @return array{ok: bool, text: string, statusCode: int|null}
     */
    public static function runPrepingIfEnabled(OutboundFeed $feed, array $requestData): array
    {
        $enabled = filter_var($feed->prepingEnabled ?? false, FILTER_VALIDATE_BOOLEAN);
        $prepingUrl = trim((string) ($feed->prepingUrl ?? ''));
        if (!$enabled || $prepingUrl === '') {
            return ['ok' => true, 'text' => '', 'statusCode' => null];
        }

        $method = strtoupper((string) ($feed->prepingHttpMethod ?? 'POST'));
        if (!in_array($method, ['GET', 'POST'], true)) {
            $method = 'POST';
        }
        $headers = self::buildPrepingAuthHeaders($feed);
        $effectiveFeedType = $method === 'GET' ? 'curlGET' : (string) ($feed->feedType ?? 'curlPOST');

        try {
            $response = self::sendRequest($effectiveFeedType, $prepingUrl, $requestData, $headers);
            if (!self::prepingResponseAllowsContinue($response['body'], $response['statusCode'])) {
                return [
                    'ok' => false,
                    'text' => sprintf(
                        'Preping rejected or invalid response (HTTP %s): %s',
                        $response['statusCode'],
                        substr($response['body'], 0, 500)
                    ),
                    'statusCode' => $response['statusCode'],
                ];
            }

            return ['ok' => true, 'text' => '', 'statusCode' => $response['statusCode']];
        } catch (\Exception $e) {
            return ['ok' => false, 'text' => 'Preping error: ' . $e->getMessage(), 'statusCode' => null];
        }
    }

    /**
     * @return array<string, string>
     */
    protected static function buildPrepingAuthHeaders(OutboundFeed $feed): array
    {
        $type = (string) ($feed->prepingAuthType ?? 'none');
        $value = (string) ($feed->prepingAuthValue ?? '');

        return match ($type) {
            'bearer' => $value !== '' ? ['Authorization' => 'Bearer ' . $value] : [],
            'basic' => $value !== '' ? ['Authorization' => 'Basic ' . base64_encode($value)] : [],
            default => [],
        };
    }

    protected static function prepingResponseAllowsContinue(string $body, int $statusCode): bool
    {
        if ($statusCode < 200 || $statusCode >= 300) {
            return false;
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) && array_key_exists('result', $decoded) && $decoded['result'] === 'true';
    }

    /**
     * @param  array<string, string>  $extraHeaders
     */
    public static function sendRequest(string $feedType, string $url, array $data, array $extraHeaders = []): array
    {
        switch ($feedType) {
            case 'curlGET':
                $querystring = http_build_query($data);
                $fullUrl = $url . (str_contains($url, '?') ? '&' : '?') . $querystring;
                $client = Http::timeout(30);
                if ($extraHeaders !== []) {
                    $client = $client->withHeaders($extraHeaders);
                }
                Log::channel('single')->info('[LiveFeed] OutboundPush: curlGET request payload', [
                    'url' => $fullUrl,
                    'headers' => $extraHeaders,
                ]);
                $response = $client->get($fullUrl);
                return [
                    'body' => $response->body(),
                    'statusCode' => $response->status(),
                    'querystring' => $querystring,
                ];

            case 'curlPOST':
            case 'curlPOST-urlencoded':
                $client = Http::timeout(30);
                if ($extraHeaders !== []) {
                    $client = $client->withHeaders($extraHeaders);
                }
                $response = $client->asForm()->post($url, $data);
                return [
                    'body' => $response->body(),
                    'statusCode' => $response->status(),
                ];

            case 'JSON':
                $headers = array_merge($extraHeaders, ['Content-Type' => 'application/json']);
                $response = Http::timeout(30)
                    ->withHeaders($headers)
                    ->withBody(json_encode($data), 'application/json')
                    ->post($url);
                return [
                    'body' => $response->body(),
                    'statusCode' => $response->status(),
                ];

            case 'csvString':
                $csv = implode(',', array_map(fn ($v) => str_replace(',', '', $v), $data));
                $fullUrl = $url . '?data=' . urlencode($csv);
                $client = Http::timeout(30);
                if ($extraHeaders !== []) {
                    $client = $client->withHeaders($extraHeaders);
                }
                $response = $client->get($fullUrl);
                return [
                    'body' => $response->body(),
                    'statusCode' => $response->status(),
                ];

            default:
                $client = Http::timeout(30);
                if ($extraHeaders !== []) {
                    $client = $client->withHeaders($extraHeaders);
                }
                $response = $client->asForm()->post($url, $data);
                return [
                    'body' => $response->body(),
                    'statusCode' => $response->status(),
                ];
        }
    }

    /**
     * Extract cost from response body using the configured cost key.
     * Supports JSON with dot notation for nested keys (e.g. "data.cost", "result.cpl").
     */
    protected static function extractCostFromResponse(string $body, string $costKey): ?float
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }
        $keys = explode('.', trim($costKey));
        $value = $decoded;
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return is_numeric($value) ? (float) $value : null;
    }

    protected static function checkSuccess(OutboundFeed $feed, string $body, int $statusCode): bool
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            if (!empty($feed->successString) && stripos($body, $feed->successString) !== false) {
                return true;
            }
            if (empty($feed->successString)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse marketplace API response into a normalized decision.
     *
     * @return array{
     *   decisionType:string,
     *   accepted:bool|null,
     *   outcome:?string,
     *   reason:?string,
     *   price:?float
     * }
     */
    protected static function parseMarketplaceDecision(string $body, int $statusCode): array
    {
        $default = [
            'decisionType' => 'pending_webhook',
            'accepted' => null,
            'outcome' => null,
            'reason' => null,
            'price' => null,
        ];

        if ($statusCode < 200 || $statusCode >= 300) {
            return array_merge($default, [
                'decisionType' => 'rejected',
                'accepted' => false,
                'reason' => sprintf('Marketplace HTTP error %s', $statusCode),
            ]);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return $default;
        }

        $outcome = strtolower(trim((string) ($decoded['outcome'] ?? '')));
        $reason = isset($decoded['reason']) ? trim((string) $decoded['reason']) : null;
        $price = isset($decoded['price']) && is_numeric($decoded['price']) ? (float) $decoded['price'] : null;

        if ($outcome === 'failure') {
            return [
                'decisionType' => 'rejected',
                'accepted' => false,
                'outcome' => $outcome,
                'reason' => $reason ?: 'Marketplace outcome=failure',
                'price' => $price,
            ];
        }

        if ($outcome === 'success') {
            if ($price !== null && $price > 0) {
                return [
                    'decisionType' => 'accepted',
                    'accepted' => true,
                    'outcome' => $outcome,
                    'reason' => 'Marketplace outcome=success',
                    'price' => $price,
                ];
            }

            return [
                'decisionType' => 'pending_manual',
                'accepted' => null,
                'outcome' => $outcome,
                'reason' => 'Marketplace outcome=success but price missing or zero',
                'price' => $price,
            ];
        }

        return array_merge($default, [
            'outcome' => $outcome !== '' ? $outcome : null,
            'reason' => $reason,
            'price' => $price,
        ]);
    }
}
