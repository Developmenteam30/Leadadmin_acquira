<?php

namespace App\Http\Controllers;

use App\Models\InboundFeed;
use App\Services\PushIncomingDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class LiveFeedController extends Controller
{
    /**
     * Show API spec as HTML page (public URL for sharing)
     * GET /live/{idFeedIn}/apispec?h={hash}
     */
    public function showApiSpec(Request $request, $idFeedIn)
    {
        $h = $request->query('h');
        if (empty($h)) {
            return response('ERROR: Please specify the security code (h parameter).', 400);
        }

        $feed = InboundFeed::with('company')->find($idFeedIn);
        if (!$feed) {
            return response('ERROR: Feed not found.', 404);
        }

        $expectedHash = hash('sha256', $feed->idFeedIn . Config::get('services.feeds.hash_salt') . $feed->password);
        if (!hash_equals($expectedHash, $h)) {
            return response('ERROR: Security code is invalid.', 403);
        }

        $fields = DB::table('fields')
            ->whereIn('fieldType', ['system', 'custom', 'inbound-export'])
            ->whereNotIn('fieldName', ['authorization', 'pswd'])
            ->orderByRaw("REPLACE(fieldName,'c_','')")
            ->get(['fieldName', 'fieldDefinition', 'fieldFormat', 'fieldDescription']);

        $requiredArray = array_merge(['pswd'], !empty($feed->required) ? explode(';', $feed->required) : []);
        $allowedArray = array_merge(['pswd'], !empty($feed->allowedFields) ? explode(';', $feed->allowedFields) : []);
        $allowedPingArray = !empty($feed->allowedPingFields) ? array_merge(['pswd'], explode(';', $feed->allowedPingFields)) : $allowedArray;

        $postingUrl = Config::get('services.feeds.posting_url');
        if (str_starts_with($postingUrl, 'http')) {
            $baseUrl = rtrim($postingUrl, '/');
        } else {
            $baseUrl = 'https://' . ltrim($postingUrl, '/');
        }
        $apiUrl = $baseUrl . '/api/live/' . $feed->idFeedIn . '/feed';
        $apiSpecUrl = $baseUrl . '/live/' . $feed->idFeedIn . '/apispec?h=' . urlencode($h);

        $companyName = $feed->company?->name ?? 'Unknown';
        $appName = Config::get('app.name', 'Lead Management');

        $findField = function ($fieldName, $param) use ($fields, $feed) {
            if ($fieldName === 'pswd') {
                return $param === 'fieldDescription' ? ($feed->password ?? '') : ($param === 'fieldDefinition' ? 'varchar(255)' : '');
            }
            foreach ($fields as $f) {
                if (($f->fieldName ?? '') === $fieldName && isset($f->$param)) {
                    if (preg_match('/^custom[1-6]$/', $fieldName)) {
                        $label = $fieldName . 'Label';
                        return ($f->$param ?? '') . (!empty($feed->$label) ? ': ' . $feed->$label : '');
                    }
                    return $f->$param ?? '';
                }
            }
            return '';
        };

        return view('live.apispec', compact(
            'feed', 'fields', 'requiredArray', 'allowedArray', 'allowedPingArray',
            'apiUrl', 'apiSpecUrl', 'companyName', 'appName', 'findField'
        ));
    }

    /**
     * Get API spec URL for a feed (for frontend to display/copy)
     */
    public function getApiSpecUrl($idFeedIn)
    {
        $feed = InboundFeed::find($idFeedIn);
        if (!$feed) {
            return response()->json(['status' => 0, 'error' => 'Feed not found'], 404);
        }

        $hash = hash('sha256', $feed->idFeedIn . Config::get('services.feeds.hash_salt') . $feed->password);
        $postingUrl = Config::get('services.feeds.posting_url');
        if (str_starts_with($postingUrl, 'http')) {
            $baseUrl = rtrim($postingUrl, '/');
        } else {
            $baseUrl = 'https://' . ltrim($postingUrl, '/');
        }

        return response()->json([
            'status' => 1,
            'data' => [
                'apiSpecUrl' => $baseUrl . '/live/' . $feed->idFeedIn . '/apispec?h=' . urlencode($hash),
                'feedUrl' => $baseUrl . '/api/live/' . $feed->idFeedIn . '/feed',
            ],
        ]);
    }

    /**
     * Submit incoming lead (public API - uses pswd for auth)
     * POST /api/live/{idFeedIn}/feed
     */
    public function submitLead(Request $request, $idFeedIn)
    {
        Log::channel('single')->info('[LiveFeed] submitLead received', [
            'idFeedIn' => $idFeedIn,
            'email' => $request->email ?? '(empty)',
        ]);

        $feed = InboundFeed::find($idFeedIn);
        if (!$feed) {
            Log::channel('single')->warning('[LiveFeed] Invalid feed id', ['idFeedIn' => $idFeedIn]);
            return $this->leadResponse(false, 'Invalid feed id', $request);
        }

        if (empty($request->pswd) || $request->pswd !== $feed->password) {
            $this->storeInboundRecord($idFeedIn, $request->all(), 'Unauthorized access...', false, $feed);
            Log::channel('single')->info('[LiveFeed] Rejected: unauthorized');
            return $this->leadResponse(false, 'Unauthorized access...', $request);
        }

        if ($feed->status === 'retired') {
            $this->storeInboundRecord($idFeedIn, $request->all(), 'This feed has been disabled.', false, $feed);
            return $this->leadResponse(false, 'This feed has been disabled.', $request);
        }

        if (!empty($feed->paused)) {
            $msg = $feed->pauseMessage ?: 'Lead rejected.';
            $this->storeInboundRecord($idFeedIn, $request->all(), $msg . ' [Status: PIF]', false, $feed);
            return $this->leadResponse(false, $msg, $request);
        }

        // Basic validation - check required fields
        $required = !empty($feed->required) ? explode(';', $feed->required) : [];
        $allowed = !empty($feed->allowedFields) ? explode(';', $feed->allowedFields) : [];

        foreach ($required as $field) {
            if ($field === 'phone') {
                if (empty($request->landline) && empty($request->cellphone)) {
                    $this->storeInboundRecord($idFeedIn, $request->all(), 'Phone (landline or cellphone) is required.', false, $feed);
                    return $this->leadResponse(false, 'Phone (landline or cellphone) is required.', $request);
                }
            } elseif (empty($request->$field)) {
                $this->storeInboundRecord($idFeedIn, $request->all(), "{$field} is a required field.", false, $feed);
                return $this->leadResponse(false, "{$field} is a required field.", $request);
            }
        }

        // Store accepted lead and get idRecord
        $idRecord = $this->storeInboundRecord($idFeedIn, $request->all(), 'Success', true, $feed);
        if (!$idRecord) {
            Log::channel('single')->error('[LiveFeed] storeInboundRecord failed');
            return $this->leadResponse(false, 'Database error while trying to add your record.', $request);
        }

        Log::channel('single')->info('[LiveFeed] Lead stored in data_inbound', ['idRecord' => $idRecord]);

        // Update stats_inbound
        $statsDay = now()->format('Y-m-d');
        $url = self::parseUrlForStats($request->url ?? '');
        DB::statement(
            'INSERT INTO stats_inbound (idFeedIn, url, stamp, accepted) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE accepted = accepted + 1',
            [$idFeedIn, $url, $statsDay]
        );

        // Push to outgoing feeds populations (same as legacy processLead.php)
        try {
            $pushResult = PushIncomingDataService::pushToOutgoingFeeds($idRecord, $idFeedIn, $request->all());
        } catch (\Throwable $e) {
            Log::channel('single')->error('[LiveFeed] pushToOutgoingFeeds exception', [
                'idRecord' => $idRecord,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        $reason = 'Successfully inserted new record.';
        if (!empty($pushResult['buyerAccepted'])) {
            Log::channel('single')->info('[LiveFeed] Buyer accepted', ['idRecord' => $idRecord]);
            return $this->leadResponse(true, $reason, $request);
        }
        if (isset($pushResult['status']) && $pushResult['status'] === 'pending') {
            $reason = $pushResult['reason'] ?? 'Lead received; awaiting buyer response.';
            DB::table('data_inbound')->where('idRecord', $idRecord)->update(['result' => 'Pending']);
            // Update stats: move from accepted to pending
            DB::statement(
                'UPDATE stats_inbound SET accepted = GREATEST(0, accepted - 1), pending = COALESCE(pending, 0) + 1 WHERE idFeedIn = ? AND url = ? AND stamp = ?',
                [$idFeedIn, $url, $statsDay]
            );
            Log::channel('single')->info('[LiveFeed] Pending', ['idRecord' => $idRecord]);
            return $this->leadResponse(false, $reason, $request, 'pending');
        }
        if (isset($pushResult['reason']) && $pushResult['reason'] !== null) {
            $reason = $pushResult['reason'];
            DB::table('data_inbound')->where('idRecord', $idRecord)->update(['result' => $reason]);
            // Update stats: move from accepted to rejected
            DB::statement(
                'UPDATE stats_inbound SET accepted = GREATEST(0, accepted - 1), rejected = rejected + 1 WHERE idFeedIn = ? AND url = ? AND stamp = ?',
                [$idFeedIn, $url, $statsDay]
            );
            Log::channel('single')->info('[LiveFeed] Push rejected, updating result', ['reason' => $reason]);
            return $this->leadResponse(false, $reason, $request);
        }
        if (isset($pushResult['status']) && $pushResult['status'] === 'queued') {
            Log::channel('single')->info('[LiveFeed] Queued for outbound (no sync buyer acceptance)', ['idRecord' => $idRecord]);
            return $this->leadResponse(false, 'Lead received; queued for outbound processing.', $request);
        }

        Log::channel('single')->info('[LiveFeed] No buyer acceptance', ['idRecord' => $idRecord]);
        return $this->leadResponse(false, $reason, $request);
    }

    /**
     * Normalize date of birth to Y-m-d for MySQL. Accepts ISO dates and common US slash/dash forms.
     */
    protected function normalizeDob(mixed $dob): ?string
    {
        if ($dob === null || $dob === '') {
            return null;
        }
        if (!is_scalar($dob)) {
            return null;
        }
        $dob = trim((string) $dob);
        if ($dob === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $dob, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }

            return null;
        }

        $formats = ['m/d/Y', 'n/j/Y', 'm-d-Y', 'n-j-Y'];
        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $dob);
                if ($parsed !== false && $parsed->format($format) === $dob) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    protected function storeInboundRecord(int $idFeedIn, array $data, string $result, bool $accepted = false, ?InboundFeed $feed = null): ?int
    {
        $stamp = $data['stamp'] ?? now()->format('Y-m-d H:i:s');
        $leadstamp = preg_match('/^\d{4}-\d{2}-\d{2}/', $stamp) ? $stamp : now()->format('Y-m-d H:i:s');

        $cost = null;
        if (isset($data['cost']) && $data['cost'] !== '' && is_numeric($data['cost'])) {
            $cost = (float) $data['cost'];
        } elseif ($feed && isset($feed->costPerLead) && (float) $feed->costPerLead > 0) {
            $cost = (float) $feed->costPerLead;
        }

        return DB::table('data_inbound')->insertGetId([
            'idFeedIn' => $idFeedIn,
            'listcode' => $data['listcode'] ?? null,
            'url' => $data['url'] ?? null,
            'ip' => $data['ip'] ?? request()->ip(),
            'leadstamp' => $leadstamp,
            'email' => $data['email'] ?? null,
            'fname' => $data['fname'] ?? null,
            'lname' => $data['lname'] ?? null,
            'addr' => $data['addr'] ?? null,
            'addr2' => $data['addr2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip' => $data['zip'] ?? null,
            'dob' => $this->normalizeDob($data['dob'] ?? null),
            'gender' => $data['gender'] ?? null,
            'landline' => $data['landline'] ?? null,
            'cellphone' => $data['cellphone'] ?? null,
            'country' => $data['country'] ?? null,
            'leadId' => $data['leadId'] ?? null,
            'custom1' => $data['custom1'] ?? null,
            'custom2' => $data['custom2'] ?? null,
            'custom3' => $data['custom3'] ?? null,
            'custom4' => $data['custom4'] ?? null,
            'custom5' => $data['custom5'] ?? null,
            'custom6' => $data['custom6'] ?? null,
            'result' => $result,
            'ping' => !empty($data['ping']) ? 1 : 0,
            'cost' => $cost,
            'rawData' => json_encode($data),
        ]);
    }

    protected static function parseUrlForStats(?string $url): string
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

    protected function leadResponse(bool $success, string $reason, Request $request, ?string $status = null)
    {
        $outFormat = strtolower($request->input('outFormat', 'xml'));

        if ($outFormat === 'json') {
            $data = ['success' => $success, 'reason' => $reason];
            if ($status !== null) {
                $data['status'] = $status;
            }
            return response()->json($data);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<response>' . "\n";
        $xml .= '  <success>' . ($success ? 'true' : 'false') . '</success>' . "\n";
        $xml .= '  <reason>' . htmlspecialchars($reason) . '</reason>' . "\n";
        if ($status !== null) {
            $xml .= '  <status>' . htmlspecialchars($status) . '</status>' . "\n";
        }
        $xml .= '</response>';

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }
}
