<?php

namespace App\Http\Middleware;

use App\Helpers\SessionHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAccessBit
{
    /**
     * Handle an incoming request.
     * Requires the authenticated user to have at least one of the specified access bits.
     * ADMIN always bypasses (has full access).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  int  ...$bits  One or more SessionHelper::LEADS_SESSION_LEVEL_* constants (e.g. STAFF, ADMIN)
     */
    public function handle(Request $request, Closure $next, int ...$bits): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'error' => 'Unauthenticated.',
            ], 401);
        }

        $accessBits = (int) ($user->accessBits ?? 0);

        // ADMIN has full access to everything
        if (SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_ADMIN)) {
            return $next($request);
        }

        foreach ($bits as $bit) {
            if (SessionHelper::checkBit($accessBits, $bit)) {
                return $next($request);
            }
        }

        return response()->json([
            'status' => 0,
            'error' => 'Insufficient access rights.',
        ], 403);
    }
}
