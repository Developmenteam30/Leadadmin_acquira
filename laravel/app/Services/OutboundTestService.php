<?php

namespace App\Services;

use App\Models\OutboundFeed;
use Illuminate\Support\Facades\Http;

class OutboundTestService
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

        if (is_string($decoded)) {
            $decodedNested = json_decode($decoded, true);
            if (is_array($decodedNested)) {
                return $decodedNested;
            }
        }

        return $fallback;
    }

    protected static $testDefaults = [
        'email' => 'test@example.com',
        'fname' => 'Test',
        'lname' => 'User',
        'addr' => '123 Main St',
        'addr2' => '',
        'city' => 'New York',
        'state' => 'NY',
        'zip' => '10001',
        'country' => 'US',
        'cellphone' => '5551234567',
        'landline' => '5559876543',
        'gender' => 'M',
        'dob' => '1990-01-15',
        'ip' => '192.168.1.1',
        'url' => 'https://example.com/test',
        'stamp' => null,
        'listcode' => 'test',
        'leadId' => 'test-1',
        'custom1' => '',
        'custom2' => '',
        'custom3' => '',
        'custom4' => '',
        'custom5' => '',
        'custom6' => '',
    ];

    /**
     * Send a test record to an outbound feed
     */
    public static function sendTest(OutboundFeed $feed, array $testData = []): array
    {
        $data = array_merge(self::$testDefaults, $testData);
        $data['stamp'] = $data['stamp'] ?? now()->format('Y-m-d H:i:s');

        $staticFields = self::normalizeArrayField($feed->staticFieldsJSON, []);
        $varFields = self::normalizeArrayField($feed->varFieldsJSON, []);
        $valueMap = self::normalizeArrayField($feed->valueMap, []);

        $requestData = [];
        foreach ($staticFields as $key => $val) {
            $requestData[$key] = $val;
        }
        foreach ($varFields as $externalKey => $internalField) {
            $val = is_array($internalField) ? ($internalField['map'] ?? $internalField['field'] ?? '') : $internalField;
            $requestData[$externalKey] = $data[$val] ?? $data[$externalKey] ?? '';
        }

        // Apply value map translations
        if (!empty($valueMap)) {
            foreach ($valueMap as $vm) {
                if (isset($vm['field'], $vm['oldValue'], $vm['newValue'], $requestData[$vm['field']])
                    && $requestData[$vm['field']] == $vm['oldValue']) {
                    $requestData[$vm['field']] = $vm['newValue'];
                }
            }
        }

        $url = $feed->postUrl;
        if (empty($url)) {
            return [
                'status' => false,
                'headers' => [],
                'querystring' => '',
                'text' => 'No post URL configured for this feed.',
            ];
        }

        $preping = OutboundPushService::runPrepingIfEnabled($feed, $requestData);
        if (!$preping['ok']) {
            return [
                'status' => false,
                'headers' => [],
                'querystring' => '',
                'text' => $preping['text'],
                'statusCode' => $preping['statusCode'],
            ];
        }

        try {
            $response = self::sendRequest($feed->feedType, $url, $requestData);
            $success = self::checkSuccess($feed, $response['body'], $response['statusCode']);
            return [
                'status' => $success,
                'headers' => $response['headers'],
                'querystring' => $response['querystring'] ?? '',
                'text' => $response['body'],
                'statusCode' => $response['statusCode'],
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'headers' => [],
                'querystring' => '',
                'text' => $e->getMessage(),
            ];
        }
    }

    protected static function sendRequest(string $feedType, string $url, array $data): array
    {
        $headers = [];
        $querystring = '';
        $body = '';

        switch ($feedType) {
            case 'curlGET':
                $querystring = http_build_query($data);
                $fullUrl = $url . (strpos($url, '?') !== false ? '&' : '?') . $querystring;
                $response = Http::timeout(30)->get($fullUrl);
                $body = $response->body();
                $headers = $response->headers();
                break;

            case 'curlPOST':
            case 'curlPOST-urlencoded':
                $response = Http::asForm()->timeout(30)->post($url, $data);
                $body = $response->body();
                $headers = $response->headers();
                break;

            case 'JSON':
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->withBody(json_encode($data), 'application/json')
                    ->timeout(30)->post($url);
                $body = $response->body();
                $headers = $response->headers();
                break;

            default:
                $response = Http::asForm()->timeout(30)->post($url, $data);
                $body = $response->body();
                $headers = $response->headers();
        }

        $headerLines = [];
        foreach ($headers as $name => $values) {
            foreach ((array) $values as $v) {
                $headerLines[] = $name . ': ' . $v;
            }
        }

        return [
            'headers' => $headerLines,
            'body' => $body,
            'statusCode' => $response->status(),
            'querystring' => $feedType === 'curlGET' ? $querystring : http_build_query($data),
        ];
    }

    protected static function checkSuccess($feed, string $body, int $statusCode): bool
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
