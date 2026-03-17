<?php

namespace App\Services;

use App\Models\InboundFeed;
use App\Models\OutboundFeed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OutboundPushService
{
    /**
     * Build request data from inbound record and feed config, then send to outbound URL.
     * Returns ['status' => bool, 'text' => string, 'fields' => array]
     */
    public static function pushRecord(object $record, OutboundFeed $feed, ?InboundFeed $inboundFeed = null): array
    {
        $staticFields = is_array($feed->staticFieldsJSON) ? $feed->staticFieldsJSON : (json_decode($feed->staticFieldsJSON ?? '{}', true) ?: []);
        $varFields = is_array($feed->varFieldsJSON) ? $feed->varFieldsJSON : (json_decode($feed->varFieldsJSON ?? '{}', true) ?: []);
        $valueMap = is_array($feed->valueMap) ? $feed->valueMap : (json_decode($feed->valueMap ?? '[]', true) ?: []);

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

        $url = $feed->postUrl;
        if (empty($url)) {
            Log::channel('single')->warning('[LiveFeed] OutboundPush: No post URL configured', [
                'idFeedOut' => $feed->idFeedOut ?? null,
            ]);
            return ['status' => false, 'text' => 'No post URL configured', 'fields' => []];
        }

        try {
            Log::channel('single')->info('[LiveFeed] OutboundPush: Sending request', [
                'idFeedOut' => $feed->idFeedOut ?? null,
                'feedType' => $feed->feedType ?? null,
                'url' => $url,
            ]);
            $response = self::sendRequest($feed->feedType, $url, $requestData);
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
            return [
                'status' => $success,
                'text' => $response['body'],
                'fields' => [],
                'cost' => $outboundCost,
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

    protected static function sendRequest(string $feedType, string $url, array $data): array
    {
        switch ($feedType) {
            case 'curlGET':
                $querystring = http_build_query($data);
                $fullUrl = $url . (str_contains($url, '?') ? '&' : '?') . $querystring;
                $response = Http::timeout(30)->get($fullUrl);
                return [
                    'body' => $response->body(),
                    'statusCode' => $response->status(),
                    'querystring' => $querystring,
                ];

            case 'curlPOST':
            case 'curlPOST-urlencoded':
                $response = Http::asForm()->timeout(30)->post($url, $data);
                return [
                    'body' => $response->body(),
                    'statusCode' => $response->status(),
                ];

            case 'JSON':
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->withBody(json_encode($data), 'application/json')
                    ->timeout(30)->post($url);
                return [
                    'body' => $response->body(),
                    'statusCode' => $response->status(),
                ];

            case 'csvString':
                $csv = implode(',', array_map(fn ($v) => str_replace(',', '', $v), $data));
                $fullUrl = $url . '?data=' . urlencode($csv);
                $response = Http::timeout(30)->get($fullUrl);
                return [
                    'body' => $response->body(),
                    'statusCode' => $response->status(),
                ];

            default:
                $response = Http::asForm()->timeout(30)->post($url, $data);
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
}
