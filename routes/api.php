<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// Import Controller yang baru dibuat
use App\Http\Controllers\Api\V1\MasterApiController;
use App\Http\Controllers\Api\V1\TicketApiController;
use App\Http\Controllers\Api\V1\BumAnalyticsApiController;
use App\Http\Controllers\Api\Mobile\Satset\MobileSatsetAuthController;
use App\Http\Controllers\Api\Mobile\Satset\MobileSatsetTicketController;
use App\Models\Master\TicketFormSchema;
use App\Http\Controllers\Master\TicketFormSchemaController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('/bum/analytics')->group(function () {
    Route::get('/summary', [BumAnalyticsApiController::class, 'summary']);
    Route::get('/usage-trend', [BumAnalyticsApiController::class, 'usageTrend']);
    Route::get('/usage-forecast', [BumAnalyticsApiController::class, 'usageForecast']);
    Route::get('/stock-forecast', [BumAnalyticsApiController::class, 'stockForecast']);
    Route::get('/request-trend', [BumAnalyticsApiController::class, 'requestTrend']);
    Route::get('/meeting-consumption-trend', [BumAnalyticsApiController::class, 'meetingConsumptionTrend']);
    Route::get('/receiving-trend', [BumAnalyticsApiController::class, 'receivingTrend']);
    Route::get('/procurement-recommendation', [BumAnalyticsApiController::class, 'procurementRecommendation']);
});

Route::prefix('mobile/v1/satset')->group(function () {
    Route::post('/auth/sso-exchange', [MobileSatsetAuthController::class, 'exchange']);

    Route::middleware('satset.mobile')->group(function () {
        Route::post('/auth/logout', [MobileSatsetAuthController::class, 'logout']);
        Route::get('/me', [MobileSatsetTicketController::class, 'me']);
        Route::get('/bootstrap', [MobileSatsetTicketController::class, 'bootstrap']);
        Route::get('/tickets', [MobileSatsetTicketController::class, 'index']);
        Route::get('/tickets/{ticket}', [MobileSatsetTicketController::class, 'show']);
        Route::get('/tickets/{ticket}/history', [MobileSatsetTicketController::class, 'history']);
        Route::post('/tickets/general', [MobileSatsetTicketController::class, 'storeGeneral']);
        Route::post('/tickets/consumption', [MobileSatsetTicketController::class, 'storeConsumption']);
        Route::post('/tickets/atk-rtk', [MobileSatsetTicketController::class, 'storeAtkRtk']);
        Route::post('/tickets/ga-request-finding', [MobileSatsetTicketController::class, 'storeGaRequestFinding']);
        Route::post('/tickets/{ticket}/comments', [MobileSatsetTicketController::class, 'comment']);
        Route::post('/tickets/{ticket}/attachments', [MobileSatsetTicketController::class, 'uploadAttachment']);
        Route::post('/tickets/{ticket}/approvals/{approval}', [MobileSatsetTicketController::class, 'approve']);
    });
});

// ========================================================================
// API V1 ROUTES
// ========================================================================

Route::prefix('v1')->group(function () {

    // --------------------------------------------------------------------
    // MASTER DATA (Public / No Auth for Testing)
    // --------------------------------------------------------------------
    // URL: GET /api/v1/master/problem-categories
    Route::get('/master/problem-categories', [MasterApiController::class, 'getProblemCategories']);
    Route::get('/master/ticket-categories', [MasterApiController::class, 'getTicketCategories']);
    Route::get('/master/ticket-status', [MasterApiController::class, 'getTicketStatus']);
    Route::get('/master/ticket-priorities', [MasterApiController::class, 'getTicketPriorities']);
    Route::get('/master/impacts', [MasterApiController::class, 'getImpacts']);
    Route::get('/master/urgencies', [MasterApiController::class, 'getUrgencies']);


    // --------------------------------------------------------------------
    // TICKET MODULE
    // --------------------------------------------------------------------
    Route::get('/tickets', [TicketApiController::class, 'index']);
    Route::get('/tickets/{id}/history', [TicketApiController::class, 'history']);
    Route::post('/tickets', [TicketApiController::class, 'store']);
    Route::put('/tickets/{id}', [TicketApiController::class, 'update']);
    Route::get('/tickets/{id}', [TicketApiController::class, 'show']);

    Route::get('/ticket-form-schema/{category}', [TicketFormSchemaController::class, 'getSchemaApi']);

    Route::prefix('/bum/analytics')->group(function () {
        Route::get('/summary', [BumAnalyticsApiController::class, 'summary']);
        Route::get('/usage-trend', [BumAnalyticsApiController::class, 'usageTrend']);
        Route::get('/usage-forecast', [BumAnalyticsApiController::class, 'usageForecast']);
        Route::get('/stock-forecast', [BumAnalyticsApiController::class, 'stockForecast']);
        Route::get('/request-trend', [BumAnalyticsApiController::class, 'requestTrend']);
        Route::get('/meeting-consumption-trend', [BumAnalyticsApiController::class, 'meetingConsumptionTrend']);
        Route::get('/receiving-trend', [BumAnalyticsApiController::class, 'receivingTrend']);
        Route::get('/procurement-recommendation', [BumAnalyticsApiController::class, 'procurementRecommendation']);
    });
    

    /*
    Route::middleware('auth:sanctum')->group(function () {
        // Route yang butuh token taruh sini
    });
    */
});
