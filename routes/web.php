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
use App\Http\Controllers\Master\ExecutiveSummaryController;
use App\Http\Controllers\Setting\TicketDataController;
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
    Route::get('settings/it-service', [\App\Http\Controllers\Setting\ItServiceController::class, 'index'])->middleware('admin.only')->name('settings.it-service.index');
    Route::put('settings/it-service', [\App\Http\Controllers\Setting\ItServiceController::class, 'update'])->middleware('admin.only')->name('settings.it-service.update');
    Route::prefix('settings/ticket-data')->name('settings.ticket-data.')->middleware('admin.only')->group(function () {
        Route::get('/', [TicketDataController::class, 'index'])->name('index');
        Route::post('/backup', [TicketDataController::class, 'backup'])->name('backup');
        Route::post('/restore', [TicketDataController::class, 'restore'])->name('restore');
        Route::delete('/clear', [TicketDataController::class, 'clear'])->name('clear');
        Route::get('/backups/{filename}', [TicketDataController::class, 'downloadSaved'])->name('backups.download');
        Route::post('/backups/{filename}/restore', [TicketDataController::class, 'restoreSaved'])->name('backups.restore');
    });

    // Page
    Route::get('dashboard', [dashboardController::class, 'index'])->name('dashboard.index');

    Route::prefix('it/configuration')->name('it.configuration.')->group(function () {
        Route::get('/pilot', \App\Http\Controllers\It\PilotController::class)->name('pilot');
        Route::get('/teams', [\App\Http\Controllers\It\TeamController::class, 'index'])->name('teams');
        Route::post('/teams', [\App\Http\Controllers\It\TeamController::class, 'store'])->name('teams.store');
        Route::put('/teams/{team}', [\App\Http\Controllers\It\TeamController::class, 'update'])->name('teams.update');
        Route::get('/', [\App\Http\Controllers\It\ConfigurationController::class, 'index'])->name('index');
        Route::post('/drafts', [\App\Http\Controllers\It\ConfigurationController::class, 'draft'])->name('draft');
        Route::get('/versions/{version}', [\App\Http\Controllers\It\ConfigurationController::class, 'show'])->name('show');
        Route::put('/versions/{version}', [\App\Http\Controllers\It\ConfigurationController::class, 'update'])->name('update');
        Route::post('/versions/{version}/publish', [\App\Http\Controllers\It\ConfigurationController::class, 'publish'])->name('publish');
        Route::post('/versions/{version}/rollback', [\App\Http\Controllers\It\ConfigurationController::class, 'rollback'])->name('rollback');
        Route::post('/versions/{version}/sla-preview', \App\Http\Controllers\It\SlaPreviewController::class)->name('sla-preview');
        Route::post('/versions/{version}/preview', [\App\Http\Controllers\It\ConfigurationController::class, 'preview'])->name('preview');
    });

    // Ticket
    Route::prefix('it')->name('it.')->controller(\App\Http\Controllers\It\WorkflowController::class)->group(function () {
        Route::get('/practices', [\App\Http\Controllers\It\PracticeController::class,'index'])->name('practices.index');
        Route::post('/practices', [\App\Http\Controllers\It\PracticeController::class,'store'])->name('practices.store');
        Route::get('/practices/{record}', [\App\Http\Controllers\It\PracticeController::class,'show'])->name('practices.show');
        Route::post('/practices/{record}/{action}', [\App\Http\Controllers\It\PracticeController::class,'action'])->name('practices.action');
        Route::get('/operations', [\App\Http\Controllers\It\OperationsController::class,'index'])->name('operations');
        Route::get('/dashboard', [\App\Http\Controllers\It\ManagementDashboardController::class,'index'])->name('dashboard');
        Route::get('/operations/health', [\App\Http\Controllers\It\OperationsController::class,'health'])->name('operations.health');
        Route::post('/operations/notifications/{notification}/read', [\App\Http\Controllers\It\OperationsController::class,'read'])->name('operations.notifications.read');
        Route::get('/', 'index')->name('index');
        Route::get('/request', 'requestEntry')->name('request');
        Route::get('/create', 'create')->name('create');
        Route::post('/form', 'form')->name('form');
        Route::post('/tickets', 'store')->name('store');
        Route::get('/tickets/{ticket}', 'show')->name('show');
        Route::get('/tickets/{ticket}/revise', 'reviseForm')->name('revise.form');
        Route::post('/tickets/{ticket}/form', 'reviseForm')->name('revise.schema');
        Route::post('/tickets/{ticket}/actions/{action}', 'action')->name('action');
        Route::get('/tickets/{ticket}/attachments/{attachment}', 'attachment')->name('attachment');
        Route::get('/approvals', 'approvals')->name('approvals');
        Route::get('/approvals/{approval}', 'approval')->name('approval');
        Route::post('/approvals/{approval}/decision', 'decide')->name('decide');
        Route::get('/notifications', 'notifications')->name('notifications');
        Route::post('/notifications/{notification}/read', 'readNotification')->name('notifications.read');
    });
    Route::get('/ticket/tiket-saya', [App\Http\Controllers\Master\TicketController::class, 'ticketsIndex'])
        ->name('ticket.mine');
    Route::redirect('/ticket/general', '/ticket/tiket-saya')
        ->name('ticket.general');
    Route::get('/ticket/ga-requests', [App\Http\Controllers\Master\TicketController::class, 'gaRequestsIndex'])
        ->middleware('ga.team')
        ->name('ticket.ga-requests');
    Route::get('/ticket/permintaan-konsumsi/create', [App\Http\Controllers\Master\TicketController::class, 'createConsumption'])
        ->name('ticket.konsumsi.create');
    Route::get('/ticket/atk-rtk/create', [App\Http\Controllers\Master\TicketController::class, 'createAtkRtk'])
        ->name('ticket.atk-rtk.create');
    Route::get('/ticket/ga-permintaan-temuan/create', [App\Http\Controllers\Master\TicketController::class, 'createGaRequestFinding'])
        ->name('ticket.ga-permintaan-temuan.create');
    Route::get('/ticket/gudang-atk-rtk', [App\Http\Controllers\Master\TicketController::class, 'warehouseAtkRtk'])
        ->middleware('ga.team')
        ->name('ticket.atk-rtk.warehouse');
    Route::prefix('bum')->name('bum.')->middleware('ga.team')->group(function () {
        Route::get('/dashboard', [BumInventoryController::class, 'dashboard'])->name('dashboard');
        Route::get('/executive-summary', [ExecutiveSummaryController::class, 'index'])->name('executive-summary');
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
        Route::get('/uoms', [BumInventoryController::class, 'uoms'])->name('uoms');
        Route::post('/uoms', [BumInventoryController::class, 'storeUom'])->name('uoms.store');
        Route::put('/uoms/{uom}', [BumInventoryController::class, 'updateUom'])->name('uoms.update');
        Route::get('/items', [BumInventoryController::class, 'items'])->name('items');
        Route::post('/items', [BumInventoryController::class, 'storeItem'])->name('items.store');
        Route::post('/items/import', [BumInventoryController::class, 'importItems'])->name('items.import');
        Route::get('/items/import/template', [BumInventoryController::class, 'downloadItemImportTemplate'])->name('items.import.template');
        Route::post('/items/{item}/stock-adjustment', [BumInventoryController::class, 'adjustItemStock'])->name('items.stock-adjustment');
        Route::get('/items/{item}', [BumInventoryController::class, 'showItem'])->name('items.show');
        Route::patch('/items/{item}/code', [BumInventoryController::class, 'updateItemCode'])->name('items.code.update');
        Route::patch('/items/{item}/status', [BumInventoryController::class, 'updateItemStatus'])->name('items.status.update');
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
    Route::get('/ticket/approvals', [App\Http\Controllers\Master\TicketController::class, 'approvalsIndex'])
        ->name('ticket.approvals');
    Route::get('/tickets/{ticket}/attachments/{attachment}', [App\Http\Controllers\Master\TicketController::class, 'viewAttachment'])
        ->name('ticket.attachments.show');
    Route::resource('ticket', App\Http\Controllers\Master\TicketController::class);
    Route::put('/tickets/{ticket}/status', [App\Http\Controllers\Master\TicketController::class, 'updateStatus'])
        ->name('ticket.updateStatus');
    Route::post('/tickets/{ticket}/assign', [App\Http\Controllers\Master\TicketController::class, 'assign'])->name('ticket.assign');
    Route::post('/tickets/{ticket}/comment', [App\Http\Controllers\Master\TicketController::class, 'comment'])->name('ticket.comment');
    Route::post('{ticket}/approve', [App\Http\Controllers\Master\TicketController::class, 'approve'])->name('ticket.approve');
    Route::post('/tickets/{ticket}/approvals/sync-manager', [App\Http\Controllers\Master\TicketController::class, 'syncManagerApproval'])
        ->name('ticket.approvals.sync-manager');
    Route::post('/tickets/{ticket}/atk-rtk/bum-review', [App\Http\Controllers\Master\TicketController::class, 'bumReviewAtkRtk'])->middleware('ga.team')->name('ticket.atk-rtk.bum-review');
    Route::post('/tickets/{ticket}/atk-rtk/replenish', [App\Http\Controllers\Master\TicketController::class, 'replenishAtkRtk'])->middleware('ga.team')->name('ticket.atk-rtk.replenish');
    Route::post('/tickets/{ticket}/atk-rtk/handover', [App\Http\Controllers\Master\TicketController::class, 'handoverAtkRtk'])->middleware('ga.team')->name('ticket.atk-rtk.handover');
    Route::get('/tickets/{ticket}/atk-rtk/goods-issue', [App\Http\Controllers\Master\TicketController::class, 'atkRtkGoodsIssue'])->name('ticket.atk-rtk.goods-issue');
    Route::get('/tickets/{ticket}/consumption/form', [App\Http\Controllers\Master\TicketController::class, 'consumptionForm'])->name('ticket.consumption.form');
    Route::post('/tickets/{ticket}/consumption/flow', [App\Http\Controllers\Master\TicketController::class, 'updateConsumptionFlow'])->middleware('ga.team')->name('ticket.consumption.flow');
    Route::post('/tickets/{ticket}/consumption/reopen', [App\Http\Controllers\Master\TicketController::class, 'reopenConsumption'])->middleware('ga.team')->name('ticket.consumption.reopen');
    Route::post('/tickets/{ticket}/consumption/evidence', [App\Http\Controllers\Master\TicketController::class, 'uploadConsumptionEvidence'])->name('ticket.consumption.evidence');
    Route::post('/tickets/{ticket}/ga-request-finding/follow-up', [App\Http\Controllers\Master\TicketController::class, 'followUpGaRequestFinding'])
        ->middleware('ga.team')
        ->name('ticket.ga-request-finding.follow-up');
    Route::get('/tickets/{ticket}/ga-request-finding/report', [App\Http\Controllers\Master\TicketController::class, 'downloadGaRequestFindingReport'])
        ->name('ticket.ga-request-finding.report');

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
    Route::post('/delete-laporan/{hashid}', [App\Http\Controllers\Report\ReportController::class, 'destroy'])->name('laporan.destroy.post');

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
