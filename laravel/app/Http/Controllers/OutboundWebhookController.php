<?php

namespace App\Http\Controllers;

use App\Models\OutboundFeed;
use App\Services\PushIncomingDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OutboundWebhookController extends Controller
{
    /**
     * Receive webhook callback from marketplace buyers with accept/reject result.
     * POST /api/webhooks/outbound
     *
     * Auth: optional; token is validated only when feed webhookSecret is configured.
     *
     * Standard body (JSON): leadId or callbackId, status accepted|rejected, optional reason, optional cost.
     *
     * Alternate buyer body: LeadId (matches callbackId from lead post), Amount (maps to cost),
     * optional SoldDate, LeadType. If status is omitted but LeadId and numeric Amount are present,
     * the callback is treated as accepted (sale notification).
     */
    public function receive(Request $request)
    {
        $leadId = $request->input('leadId')
            ?? $request->input('callbackId')
            ?? $request->input('LeadId');
        if ($leadId === null || $leadId === '') {
            return response()->json(['success' => false, 'error' => 'leadId, callbackId, or LeadId is required in the request body'], 422);
        }

        $payload = $request->all();
        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        if (!in_array($status, ['accepted', 'rejected'], true)) {
            $amountPresent = array_key_exists('Amount', $payload) && is_numeric($payload['Amount']);
            if ($amountPresent) {
                $status = 'accepted';
            } else {
                return response()->json(['success' => false, 'error' => 'status must be accepted or rejected (or send Amount with LeadId for sale callbacks)'], 422);
            }
        }

        $reason = (string) ($payload['reason'] ?? '');

        $cost = null;
        if (array_key_exists('cost', $payload) && is_numeric($payload['cost'])) {
            $cost = (float) $payload['cost'];
        } elseif (array_key_exists('Amount', $payload) && is_numeric($payload['Amount'])) {
            $cost = (float) $payload['Amount'];
        }

        $row = DB::table('data_outbound')
            ->where('webhookCallbackId', $leadId)
            ->orderByDesc('idRecord')
            ->first();

        if (!$row) {
            Log::channel('single')->info('[Webhook] Lead ID not found or already processed', ['leadId' => $leadId]);
            return response()->json(['success' => false, 'error' => 'Record not found or already processed'], 404);
        }

        $idFeedOut = (int) $row->idFeedOut;
        $feed = OutboundFeed::find($idFeedOut);
        if (!$feed) {
            Log::channel('single')->warning('[Webhook] Feed not found', ['idFeedOut' => $idFeedOut]);
            return response()->json(['success' => false, 'error' => 'Feed not found'], 404);
        }

        if (($feed->responseType ?? 'realtime') !== 'marketplace') {
            Log::channel('single')->warning('[Webhook] Feed is not marketplace type', ['idFeedOut' => $idFeedOut]);
            return response()->json(['success' => false, 'error' => 'Feed does not accept webhooks'], 400);
        }

        $token = $request->header('X-Webhook-Token')
            ?? (preg_match('/^Bearer\s+(.+)$/i', $request->header('Authorization', ''), $m) ? trim($m[1] ?? '') : null);

        $secret = (string) ($feed->webhookSecret ?? '');
        if ($secret !== '' && !hash_equals($secret, (string) $token)) {
            Log::channel('single')->warning('[Webhook] Invalid token', ['idFeedOut' => $idFeedOut]);
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $idRecord = $row->idRecord;
        $accepted = $status === 'accepted';
        $resultText = $accepted ? 'Webhook accepted' : ($reason !== '' ? $reason : 'Webhook rejected');

        $buyerPayloadRaw = $request->getContent();
        if ($buyerPayloadRaw === '') {
            $buyerPayloadRaw = json_encode($request->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        try {
            PushIncomingDataService::processWebhookCallback(
                (int) $idRecord,
                (int) $idFeedOut,
                $accepted,
                $resultText,
                $cost,
                $buyerPayloadRaw
            );

            Log::channel('single')->info('[Webhook] Processed callback', [
                'idFeedOut' => $idFeedOut,
                'idRecord' => $idRecord,
                'status' => $status,
                'overrideProcessed' => ((int) ($row->processed ?? 0) === 1),
            ]);

            return response()->json([
                'success' => $accepted,
                'message' => $accepted ? 'Lead accepted' : 'Lead rejected',
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[Webhook] Failed to process callback', [
                'idFeedOut' => $idFeedOut,
                'leadId' => $leadId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => 'Processing failed'], 500);
        }
    }
}

