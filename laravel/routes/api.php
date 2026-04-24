<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Jobs\DemoQueueLogJob;
use App\Http\Controllers\LiveFeedController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login']);

// Public live feed API (no auth - uses pswd for validation)
Route::post('/live/{idFeedIn}/feed', [LiveFeedController::class, 'submitLead']);

// Public webhook for marketplace outbound feeds (auth via X-Webhook-Token or Bearer)
// Lead ID (callbackId) is in the request body, not the URL
Route::post('/webhooks/outbound', [\App\Http\Controllers\OutboundWebhookController::class, 'receive']);
Route::post('/demo-queue-log', function (Request $request) {
    DemoQueueLogJob::dispatch(
        auth()->id(),
        $request->input('message', 'Demo queue job executed successfully.')
    );

    return response()->json([
        'success' => true,
        'message' => 'Demo queue log job dispatched successfully.',
        'queued' => true,
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LogoutController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // User Management (ADMIN only)
    Route::middleware('require.access.bit:' . \App\Helpers\SessionHelper::LEADS_SESSION_LEVEL_ADMIN)->group(function () {
        Route::get('/users', [\App\Http\Controllers\UserController::class, 'index']);
        Route::get('/users/bits', [\App\Http\Controllers\UserController::class, 'getBits']);
        Route::get('/users/{id}', [\App\Http\Controllers\UserController::class, 'show']);
        Route::post('/users', [\App\Http\Controllers\UserController::class, 'store']);
        Route::put('/users/{id}', [\App\Http\Controllers\UserController::class, 'update']);
    });

    // STAFF or higher: Companies, Feeds, Dashboard, Verticals, etc.
    Route::middleware('require.access.bit:' . \App\Helpers\SessionHelper::LEADS_SESSION_LEVEL_STAFF)->group(function () {
        Route::get('/dashboard/life-leads', [\App\Http\Controllers\DashboardController::class, 'getLifeLeads']);

        // Vertical Management
            Route::get('/verticals', [\App\Http\Controllers\VerticalController::class, 'index']);
        Route::get('/verticals/divisions', [\App\Http\Controllers\VerticalController::class, 'getDivisions']);
        Route::get('/verticals/{id}', [\App\Http\Controllers\VerticalController::class, 'show']);
        Route::post('/verticals', [\App\Http\Controllers\VerticalController::class, 'store']);
        Route::put('/verticals/{id}', [\App\Http\Controllers\VerticalController::class, 'update']);

        // Division Management
        Route::post('/divisions', [\App\Http\Controllers\DivisionController::class, 'store']);

        // Field Management
        Route::get('/fields', [\App\Http\Controllers\FieldController::class, 'index']);
        Route::get('/fields/{id}', [\App\Http\Controllers\FieldController::class, 'show']);
        Route::post('/fields', [\App\Http\Controllers\FieldController::class, 'store']);
        Route::put('/fields/{id}', [\App\Http\Controllers\FieldController::class, 'update']);

        // Company Management
        Route::get('/companies', [\App\Http\Controllers\CompanyController::class, 'index']);
        Route::get('/companies/dropdown', [\App\Http\Controllers\CompanyController::class, 'getDropdown']);
        Route::get('/companies/countries', [\App\Http\Controllers\CompanyController::class, 'getCountries']);
        Route::get('/companies/staff-users', [\App\Http\Controllers\CompanyController::class, 'getStaffUsers']);
        Route::get('/companies/{id}', [\App\Http\Controllers\CompanyController::class, 'show']);
        Route::get('/companies/{id}/notes', [\App\Http\Controllers\CompanyController::class, 'getNotes']);
        Route::post('/companies', [\App\Http\Controllers\CompanyController::class, 'store']);
        Route::post('/companies/{id}/notes', [\App\Http\Controllers\CompanyController::class, 'addNote']);
        Route::put('/companies/{id}', [\App\Http\Controllers\CompanyController::class, 'update']);

        // Incoming Feeds Management
        Route::get('/inbound-feeds', [\App\Http\Controllers\InboundFeedController::class, 'index']);
        Route::get('/inbound-feeds/ping', [\App\Http\Controllers\InboundFeedController::class, 'ping']);
        Route::get('/inbound-feeds/categories', [\App\Http\Controllers\InboundFeedController::class, 'getCategories']);
        Route::get('/inbound-feeds/available-fields', [\App\Http\Controllers\InboundFeedController::class, 'getAvailableFields']);
        Route::get('/inbound-feeds/timezones', [\App\Http\Controllers\InboundFeedController::class, 'getTimezones']);
        Route::get('/inbound-feeds/{id}', [\App\Http\Controllers\InboundFeedController::class, 'show']);
        Route::post('/inbound-feeds', [\App\Http\Controllers\InboundFeedController::class, 'store']);
        Route::put('/inbound-feeds/{id}', [\App\Http\Controllers\InboundFeedController::class, 'update']);
        Route::patch('/inbound-feeds/{id}/toggle-pause', [\App\Http\Controllers\InboundFeedController::class, 'togglePause']);
        Route::get('/inbound-feeds/{id}/api-spec', [\App\Http\Controllers\InboundFeedController::class, 'getApiSpec']);
        Route::get('/inbound-feeds/{id}/ping-spec', [\App\Http\Controllers\InboundFeedController::class, 'getPingSpec']);
        Route::get('/inbound-feeds/{id}/filter-zip', [\App\Http\Controllers\InboundFeedController::class, 'getFilterZip']);
        Route::get('/inbound-feeds/{id}/api-spec-url', [LiveFeedController::class, 'getApiSpecUrl']);
        Route::get('/record-search/feeds', [\App\Http\Controllers\RecordSearchController::class, 'getFeeds']);
        Route::get('/record-search', [\App\Http\Controllers\RecordSearchController::class, 'search']);
        Route::get('/record-search/outbound-feeds', [\App\Http\Controllers\RecordSearchController::class, 'getOutboundFeeds']);
        Route::get('/record-search/outbound', [\App\Http\Controllers\RecordSearchController::class, 'searchOutbound']);
        Route::post('/record-search/outbound/{idRecord}/{idFeedOut}/confirm-marketplace', [\App\Http\Controllers\RecordSearchController::class, 'confirmMarketplacePending']);
        Route::get('/record-search/outbound/{idRecord}/{idFeedOut}/buyer-payload', [\App\Http\Controllers\RecordSearchController::class, 'getOutboundBuyerPayload']);
        Route::post('/record-search/outbound/{idRecord}/{idFeedOut}/resend', [\App\Http\Controllers\RecordSearchController::class, 'resendOutboundRecord']);
        Route::post('/inbound-feeds/{id}/import-filter-zip', [\App\Http\Controllers\InboundFeedController::class, 'importFilterZip']);
        Route::get('/inbound-feeds/{id}/populations', [\App\Http\Controllers\FeedPopulationController::class, 'indexByInbound']);
        Route::post('/inbound-feeds/{id}/populations', [\App\Http\Controllers\FeedPopulationController::class, 'storeByInbound']);
        Route::get('/inbound-feeds/{id}/url-list', [\App\Http\Controllers\InboundFeedController::class, 'getUrlList']);
        Route::get('/inbound-feeds/{id}/url-report', [\App\Http\Controllers\InboundFeedController::class, 'getUrlReport']);
        Route::get('/inbound-feeds/{id}/export-columns', [\App\Http\Controllers\InboundFeedController::class, 'getExportColumns']);
        Route::post('/inbound-feeds/{id}/export', [\App\Http\Controllers\InboundFeedController::class, 'createExport']);
        Route::post('/inbound-feeds/{id}/import', [\App\Http\Controllers\InboundFeedController::class, 'createImport']);

        // Outgoing Feeds Management
        Route::get('/outbound-feeds', [\App\Http\Controllers\OutboundFeedController::class, 'index']);
        Route::get('/outbound-feeds/ping', [\App\Http\Controllers\OutboundFeedController::class, 'ping']);
        Route::get('/outbound-feeds/categories', [\App\Http\Controllers\OutboundFeedController::class, 'getCategories']);
        Route::get('/outbound-feeds/feed-types', [\App\Http\Controllers\OutboundFeedController::class, 'getFeedTypes']);
        Route::get('/outbound-feeds/available-fields', [\App\Http\Controllers\OutboundFeedController::class, 'getAvailableFields']);
        Route::get('/outbound-feeds/timezones', [\App\Http\Controllers\OutboundFeedController::class, 'getTimezones']);
        Route::get('/outbound-feeds/{id}', [\App\Http\Controllers\OutboundFeedController::class, 'show']);
        Route::post('/outbound-feeds', [\App\Http\Controllers\OutboundFeedController::class, 'store']);
        Route::put('/outbound-feeds/{id}', [\App\Http\Controllers\OutboundFeedController::class, 'update']);
        Route::patch('/outbound-feeds/{id}/toggle-cron', [\App\Http\Controllers\OutboundFeedController::class, 'toggleCron']);
        Route::patch('/outbound-feeds/{id}/toggle-status', [\App\Http\Controllers\OutboundFeedController::class, 'toggleStatus']);
        Route::post('/outbound-feeds/{id}/send-test', [\App\Http\Controllers\OutboundFeedController::class, 'sendTestRecord']);
        Route::get('/outbound-feeds/{id}/queue-preview', [\App\Http\Controllers\OutboundFeedController::class, 'queuePreview']);
        Route::post('/outbound-feeds/{id}/clear-queue', [\App\Http\Controllers\OutboundFeedController::class, 'clearQueue']);
        Route::get('/outbound-feeds/{id}/url-list', [\App\Http\Controllers\OutboundFeedController::class, 'getUrlList']);
        Route::get('/outbound-feeds/{id}/url-report', [\App\Http\Controllers\OutboundFeedController::class, 'getUrlReport']);
        Route::get('/outbound-feeds/{id}/export-columns', [\App\Http\Controllers\OutboundFeedController::class, 'getExportColumns']);
        Route::post('/outbound-feeds/{id}/export', [\App\Http\Controllers\OutboundFeedController::class, 'createExport']);
        Route::post('/outbound-feeds/{id}/import', [\App\Http\Controllers\OutboundFeedController::class, 'createImport']);
        Route::post('/outbound-feeds/{id}/upload', [\App\Http\Controllers\OutboundFeedController::class, 'createUpload']);
        Route::post('/outbound-feeds/{id}/retry-rejections', [\App\Http\Controllers\OutboundFeedController::class, 'retryRejections']);
        Route::post('/outbound-feeds/{id}/resend-pending-marketplace', [\App\Http\Controllers\OutboundFeedController::class, 'resendPendingMarketplace']);
        Route::get('/outbound-feeds/{id}/resend-pending-marketplace/{jobId}', [\App\Http\Controllers\OutboundFeedController::class, 'resendPendingMarketplaceStatus']);

        // Feed Populations (for outbound feeds)
        Route::get('/outbound-feeds/{idFeedOut}/populations', [\App\Http\Controllers\FeedPopulationController::class, 'index']);
        Route::get('/feed-populations/inbound-feeds', [\App\Http\Controllers\FeedPopulationController::class, 'getInboundFeeds']);
        Route::get('/feed-populations/outbound-feeds', [\App\Http\Controllers\FeedPopulationController::class, 'getOutboundFeeds']);
        Route::get('/feed-populations/categories', [\App\Http\Controllers\FeedPopulationController::class, 'getFeedCategories']);
        Route::post('/outbound-feeds/{idFeedOut}/populations', [\App\Http\Controllers\FeedPopulationController::class, 'store']);
        Route::put('/feed-populations/{idAssoc}', [\App\Http\Controllers\FeedPopulationController::class, 'update']);
        Route::patch('/feed-populations/{idAssoc}/toggle', [\App\Http\Controllers\FeedPopulationController::class, 'toggle']);
        Route::delete('/feed-populations/{idAssoc}', [\App\Http\Controllers\FeedPopulationController::class, 'destroy']);
    });
});
