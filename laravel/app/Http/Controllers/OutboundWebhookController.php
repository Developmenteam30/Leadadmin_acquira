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
     * Auth: X-Webhook-Token or Authorization: Bearer {webhookSecret}
     * Body (JSON): { "leadId": "..." or "callbackId": "...", "status": "accepted"|"rejected", "reason": "...", "cost": 12.50 }
     * Lead ID (callbackId) is looked up from the body - no feed ID in the URL.
     */
    public function receive(Request $request)
    {
        $leadId = $request->input('leadId') ?? $request->input('callbackId');
        if (empty($leadId)) {
            return response()->json(['success' => false, 'error' => 'leadId or callbackId is required in the request body'], 422);
        }

        $status = strtolower(trim($request->input('status', '')));
        if (!in_array($status, ['accepted', 'rejected'])) {
            return response()->json(['success' => false, 'error' => 'status must be accepted or rejected'], 422);
        }

        $reason = $request->input('reason', '');
        $cost = $request->has('cost') && is_numeric($request->cost) ? (float) $request->cost : null;

        $row = DB::table('data_outbound')
            ->where('webhookCallbackId', $leadId)
            ->where('processed', 0)
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

        if (empty($feed->webhookSecret) || !hash_equals((string) $feed->webhookSecret, (string) $token)) {
            Log::channel('single')->warning('[Webhook] Invalid or missing token', ['idFeedOut' => $idFeedOut]);
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $idRecord = $row->idRecord;
        $accepted = $status === 'accepted';
        $resultText = $accepted ? 'Webhook accepted' : ($reason ?: 'Webhook rejected');

        try {
            PushIncomingDataService::processWebhookCallback(
                (int) $idRecord,
                (int) $idFeedOut,
                $accepted,
                $resultText,
                $cost
            );

            $idFeedIn = $row->idFeedIn ?? null;
            if ($idFeedIn) {
                $inboundResult = $accepted ? 'Success' : sprintf('Third-party rejection [Reason: %s] [Code: O%d0]', $resultText, $idFeedOut);
                DB::table('data_inbound')->where('idRecord', $idRecord)->update(['result' => $inboundResult]);
            }

            Log::channel('single')->info('[Webhook] Processed callback', [
                'idFeedOut' => $idFeedOut,
                'idRecord' => $idRecord,
                'status' => $status,
            ]);

            return response()->json(['success' => true, 'message' => 'Callback processed']);
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

