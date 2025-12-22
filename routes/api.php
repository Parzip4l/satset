<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// Import Controller yang baru dibuat
use App\Http\Controllers\Api\V1\MasterApiController;
use App\Http\Controllers\Api\V1\TicketApiController;

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

    /*
    Route::middleware('auth:sanctum')->group(function () {
        // Route yang butuh token taruh sini
    });
    */
});