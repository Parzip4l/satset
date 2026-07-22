<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Produck\ProdukController;
use App\Http\Controllers\General\dashboardController;
use App\Http\Controllers\General\userController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MicrosoftSsoController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\Master\TicketFormSchema;

use App\Http\Controllers\TestLdapController;
use App\Http\Controllers\LdapLoginController;
use App\Http\Controllers\Master\NotificationController;
use App\Http\Controllers\MeetingRoomController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\VCardController;
use App\Http\Controllers\Master\TicketFormSchemaController;
use App\Http\Controllers\Master\BumInventoryController;
use App\Http\Controllers\Master\BumAnalyticsController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

require __DIR__ . '/auth.php';

Route::get('/login', [LdapLoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LdapLoginController::class, 'login'])->name('login.attempt');
Route::get('/auth/microsoft/redirect', [MicrosoftSsoController::class, 'redirect'])
    ->middleware('guest')
    ->name('auth.microsoft.redirect');
Route::get('/auth/microsoft/callback', [MicrosoftSsoController::class, 'callback'])
    ->middleware('guest')
    ->name('auth.microsoft.callback');
Route::get('/public', fn () => redirect()->route('public.requests'))
    ->name('public.index');
Route::get('/public/requests', [App\Http\Controllers\Master\TicketController::class, 'createPublicRequests'])
    ->name('public.requests');
Route::get('/public/requests/konsumsi', [App\Http\Controllers\Master\TicketController::class, 'createPublicConsumption'])
    ->name('public.ticket.konsumsi.create');
Route::post('/public/requests/konsumsi', [App\Http\Controllers\Master\TicketController::class, 'storePublicConsumption'])
    ->middleware('throttle:30,1')
    ->name('public.ticket.konsumsi.store');
Route::get('/public/requests/atk-rtk', [App\Http\Controllers\Master\TicketController::class, 'createPublicAtkRtk'])
    ->name('public.ticket.atk-rtk.create');
Route::post('/public/requests/atk-rtk', [App\Http\Controllers\Master\TicketController::class, 'storePublicAtkRtk'])
    ->middleware('throttle:30,1')
    ->name('public.ticket.atk-rtk.store');
Route::get('/public/requests/ga-permintaan-temuan', [App\Http\Controllers\Master\TicketController::class, 'createPublicGaRequestFinding'])
    ->name('public.ticket.ga-permintaan-temuan.alias');
Route::get('/public/ga-permintaan-temuan', [App\Http\Controllers\Master\TicketController::class, 'createPublicGaRequestFinding'])
    ->name('public.ticket.ga-permintaan-temuan.create');
Route::post('/public/ga-permintaan-temuan', [App\Http\Controllers\Master\TicketController::class, 'storePublicGaRequestFinding'])
    ->middleware('throttle:30,1')
    ->name('public.ticket.ga-permintaan-temuan.store');

Route::get('/', function () {
    return redirect()->route('dashboard.index');
});

Route::get('/dashboard', function () {
    return view('dashboard.index');
})->name('dashboard')->middleware('auth');

Route::group(['prefix' => '/', 'middleware' => 'auth'], function () {
    // Setting
    Route::resource('menu', App\Http\Controllers\Setting\MenuController::class);
        Route::post('/update-status/{id}', [App\Http\Controllers\Setting\MenuController::class, 'updateStatus'])->name('update.status');
    Route::resource('user', App\Http\Controllers\General\userController::class);
    Route::resource('role', App\Http\Controllers\Setting\RoleController::class);

    // Page
    Route::get('dashboard', [dashboardController::class, 'index'])->name('dashboard.index');

    // Ticket
    Route::get('/ticket/general', [App\Http\Controllers\Master\TicketController::class, 'generalIndex'])
        ->name('ticket.general');
    Route::get('/ticket/permintaan-konsumsi/create', [App\Http\Controllers\Master\TicketController::class, 'createConsumption'])
        ->name('ticket.konsumsi.create');
    Route::get('/ticket/atk-rtk/create', [App\Http\Controllers\Master\TicketController::class, 'createAtkRtk'])
        ->name('ticket.atk-rtk.create');
    Route::get('/ticket/ga-permintaan-temuan/create', [App\Http\Controllers\Master\TicketController::class, 'createGaRequestFinding'])
        ->name('ticket.ga-permintaan-temuan.create');
    Route::get('/ticket/gudang-atk-rtk', [App\Http\Controllers\Master\TicketController::class, 'warehouseAtkRtk'])
        ->name('ticket.atk-rtk.warehouse');
    Route::prefix('bum')->name('bum.')->group(function () {
        Route::get('/dashboard', [BumInventoryController::class, 'dashboard'])->name('dashboard');
        Route::get('/manual-guide', fn () => view('bum.manual-guide'))->name('guide');
        Route::get('/analytics', [BumAnalyticsController::class, 'index'])->name('analytics');
        Route::prefix('analytics/data')->name('analytics.data.')->group(function () {
            Route::get('/summary', [BumAnalyticsController::class, 'summary'])->name('summary');
            Route::get('/usage-trend', [BumAnalyticsController::class, 'usageTrend'])->name('usage-trend');
            Route::get('/usage-forecast', [BumAnalyticsController::class, 'usageForecast'])->name('usage-forecast');
            Route::get('/stock-forecast', [BumAnalyticsController::class, 'stockForecast'])->name('stock-forecast');
            Route::get('/request-trend', [BumAnalyticsController::class, 'requestTrend'])->name('request-trend');
            Route::get('/meeting-consumption-trend', [BumAnalyticsController::class, 'meetingConsumptionTrend'])->name('meeting-consumption-trend');
            Route::get('/receiving-trend', [BumAnalyticsController::class, 'receivingTrend'])->name('receiving-trend');
            Route::get('/procurement-recommendation', [BumAnalyticsController::class, 'procurementRecommendation'])->name('procurement-recommendation');
        });
        Route::get('/items', [BumInventoryController::class, 'items'])->name('items');
        Route::post('/items', [BumInventoryController::class, 'storeItem'])->name('items.store');
        Route::post('/items/{item}/stock-adjustment', [BumInventoryController::class, 'adjustItemStock'])->name('items.stock-adjustment');
        Route::get('/items/{item}', [BumInventoryController::class, 'showItem'])->name('items.show');
        Route::put('/items/{item}', [BumInventoryController::class, 'updateItem'])->name('items.update');
        Route::get('/stock-card', [BumInventoryController::class, 'stockCard'])->name('stock-card');
        Route::get('/receivings', [BumInventoryController::class, 'receivings'])->name('receivings');
        Route::post('/receivings', [BumInventoryController::class, 'storeReceiving'])->name('receivings.store');
        Route::post('/receivings/{receiving}/receive', [BumInventoryController::class, 'receive'])->name('receivings.receive');
        Route::get('/opnames', [BumInventoryController::class, 'opnames'])->name('opnames');
        Route::post('/opnames', [BumInventoryController::class, 'storeOpname'])->name('opnames.store');
        Route::get('/reports', [BumInventoryController::class, 'reports'])->name('reports');
    });
    Route::post('/mail/test-email', [App\Http\Controllers\Master\TicketController::class, 'sendTestEmail'])
        ->name('mail.test.send');
    Route::resource('ticket', App\Http\Controllers\Master\TicketController::class);
    Route::put('/tickets/{ticket}/status', [App\Http\Controllers\Master\TicketController::class, 'updateStatus'])
        ->name('ticket.updateStatus');
    Route::post('/tickets/{ticket}/assign', [App\Http\Controllers\Master\TicketController::class, 'assign'])->name('ticket.assign');
    Route::post('/tickets/{ticket}/comment', [App\Http\Controllers\Master\TicketController::class, 'comment'])->name('ticket.comment');
    Route::post('{ticket}/approve', [App\Http\Controllers\Master\TicketController::class, 'approve'])->name('ticket.approve');
    Route::post('/tickets/{ticket}/atk-rtk/bum-review', [App\Http\Controllers\Master\TicketController::class, 'bumReviewAtkRtk'])->name('ticket.atk-rtk.bum-review');
    Route::post('/tickets/{ticket}/atk-rtk/handover', [App\Http\Controllers\Master\TicketController::class, 'handoverAtkRtk'])->name('ticket.atk-rtk.handover');
    Route::post('/tickets/{ticket}/consumption/flow', [App\Http\Controllers\Master\TicketController::class, 'updateConsumptionFlow'])->name('ticket.consumption.flow');
    Route::post('/tickets/{ticket}/consumption/evidence', [App\Http\Controllers\Master\TicketController::class, 'uploadConsumptionEvidence'])->name('ticket.consumption.evidence');

    Route::get('/ticket-form-schema/{category}', function ($category) {
        return TicketFormSchema::where('ticket_category_id', $category)
            ->firstOrFail()
            ->schema;
    });

    Route::resource('form-schema', TicketFormSchemaController::class);

    // Master
        Route::post('divisi/sync-signal', [App\Http\Controllers\Master\DivisionController::class, 'syncSignal'])->name('divisi.sync-signal');
        Route::resource('divisi', App\Http\Controllers\Master\DivisionController::class);
        Route::resource('lokasi', App\Http\Controllers\Master\LocationController::class);
        Route::resource('pic', App\Http\Controllers\Master\PicController::class);
            Route::post('/user/pic-update', [App\Http\Controllers\Master\PicController::class, 'updatePIC'])->name('pic.dataupdate');
        // Route::resource('hazard', App\Http\Controllers\Master\HazardController::class);
        Route::resource('observation', App\Http\Controllers\Master\ObservationController::class);
        // Route::resource('bahaya', App\Http\Controllers\Master\KategoriBahaya::class);

        // Departement
        Route::post('department/sync-signal', [App\Http\Controllers\Master\DepartmentController::class, 'syncSignal'])->name('department.sync-signal');
        Route::resource('department', App\Http\Controllers\Master\DepartmentController::class);
        Route::resource('department-problem-assign', App\Http\Controllers\Master\DepartmentCategoryController::class);
        // Problem Category
        Route::resource('problem-category', App\Http\Controllers\Master\ProblemCategoryController::class);

        // Support Master
        Route::resource('prioritas', App\Http\Controllers\Support\PriorityController::class);
        Route::resource('status', App\Http\Controllers\Support\StatusController::class);
        Route::resource('impact', App\Http\Controllers\Support\ImpactController::class);
        Route::resource('urgency', App\Http\Controllers\Support\UrgencyController::class);

    // Laporan
    Route::resource('laporan', App\Http\Controllers\Report\ReportController::class);

    Route::post('/laporan/{hashid}/review-qshe', [App\Http\Controllers\Report\ReportController::class, 'reviewByQshe'])->name('laporan.review.qshe');
    Route::post('/laporan/{hashid}/review-pic', [App\Http\Controllers\Report\ReportController::class, 'reviewByPic'])->name('laporan.review.pic');
    Route::post('/laporan/{hashid}/submited-pic', [App\Http\Controllers\Report\ReportController::class, 'progresByPic'])->name('laporan.submit.pic');
    Route::post('/laporan/{hashid}/review-submit-pic', [App\Http\Controllers\Report\ReportController::class, 'reviewProgress'])->name('laporan.review-submit.pic');
    Route::post('/delete-laporan/{hashid}', [App\Http\Controllers\Report\ReportController::class, 'destroy'])->name('laporan.destroy');

    Route::get('/get-pic-by-division/{id}', [App\Http\Controllers\Report\ReportController::class, 'getPicByDivision']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read/{id}', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/clear', [NotificationController::class, 'clearAll']);
    Route::post('/laporan/reminder', [App\Http\Controllers\Report\ReportController::class, 'sendReminder'])->name('laporan.reminder');

    // Meeting Room Booking
    Route::resource('meeting-rooms', MeetingRoomController::class);
        Route::get('/booking-calendar', [BookingController::class, 'index'])->name('booking.calendar');
        Route::get('/booking-events', [BookingController::class, 'getEvents'])->name('booking.events');
        Route::post('/booking-store', [BookingController::class, 'store'])->name('booking.store');
        Route::delete('/booking-delete/{id}', [BookingController::class, 'destroy'])->name('booking.delete');

    // VCard Routes
    Route::get('/contact/{id}', [VCardController::class, 'show'])->name('vcard.show');
    Route::get('/contact/{id}/download', [VCardController::class, 'download'])->name('vcard.download');

});
