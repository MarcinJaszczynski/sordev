<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Client\AgreementController as ClientAgreementController;
use App\Http\Controllers\Api\V1\Client\ExtrasController as ClientExtrasController;
use App\Http\Controllers\Api\V1\Client\GroupPaymentsController as ClientGroupPaymentsController;
use App\Http\Controllers\Api\V1\Client\InquiryController as ClientInquiryController;
use App\Http\Controllers\Api\V1\Client\InvoiceRequestController as ClientInvoiceRequestController;
use App\Http\Controllers\Api\V1\Client\ParticipantsController as ClientParticipantsController;
use App\Http\Controllers\Api\V1\Client\PaymentsController as ClientPaymentsController;
use App\Http\Controllers\Api\V1\Client\TripController as ClientTripController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Pilot\AttendanceController as PilotAttendanceController;
use App\Http\Controllers\Api\V1\Pilot\ChecklistController as PilotChecklistController;
use App\Http\Controllers\Api\V1\Pilot\DocumentsController as PilotDocumentsController;
use App\Http\Controllers\Api\V1\Pilot\HotelPlanController as PilotHotelPlanController;
use App\Http\Controllers\Api\V1\Pilot\InquiryController as PilotInquiryController;
use App\Http\Controllers\Api\V1\Pilot\PdfController as PilotPdfController;
use App\Http\Controllers\Api\V1\Pilot\SettlementController as PilotSettlementController;
use App\Http\Controllers\Api\V1\Pilot\TripController as PilotTripController;
use App\Http\Controllers\Api\V1\Public\InquiryController as PublicInquiryController;
use App\Http\Controllers\Api\V1\Public\PackageController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Support\Api\ApiAbilities;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/token', [AuthController::class, 'token']);
        });
    });

    Route::prefix('public')->middleware('throttle:60,1')->group(function () {
        Route::get('/packages', [PackageController::class, 'index']);
        Route::get('/packages/{package}', [PackageController::class, 'show']);
        Route::get('/regions', [PackageController::class, 'regions']);
        Route::post('/inquiries', [PublicInquiryController::class, 'store'])->middleware('throttle:10,1');
    });

    Route::prefix('pilot')->middleware([
        'auth:sanctum',
        'api.ability:'.ApiAbilities::PILOT_READ,
        'api.pilot',
        'throttle:60,1',
    ])->group(function () {
        Route::get('/trips', [PilotTripController::class, 'index']);
        Route::get('/trips/{event}', [PilotTripController::class, 'show']);
        Route::get('/trips/{event}/program', [PilotTripController::class, 'program']);

        Route::get('/trips/{event}/attendance', [PilotAttendanceController::class, 'show']);
        Route::put('/trips/{event}/attendance', [PilotAttendanceController::class, 'update'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);

        Route::get('/trips/{event}/checklist', [PilotChecklistController::class, 'show']);
        Route::post('/trips/{event}/checklist/{task}/toggle', [PilotChecklistController::class, 'toggle'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);

        Route::get('/trips/{event}/documents', [PilotDocumentsController::class, 'index']);
        Route::get('/trips/{event}/pdf/{audience}', [PilotPdfController::class, 'download'])
            ->where('audience', 'pilot|folder');

        Route::get('/trips/{event}/hotel-plan', [PilotHotelPlanController::class, 'show']);
        Route::put('/trips/{event}/hotel-plan/room-numbers', [PilotHotelPlanController::class, 'updateRoomNumbers'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);

        Route::get('/trips/{event}/inquiries', [PilotInquiryController::class, 'index']);
        Route::post('/trips/{event}/inquiries', [PilotInquiryController::class, 'store'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);

        Route::get('/trips/{event}/settlement', [PilotSettlementController::class, 'show']);
        Route::put('/trips/{event}/settlement/report', [PilotSettlementController::class, 'updateReport'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);
        Route::post('/trips/{event}/settlement/expenses', [PilotSettlementController::class, 'storeExpense'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);
        Route::put('/trips/{event}/settlement/expenses/{cost}', [PilotSettlementController::class, 'updateExpense'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);
        Route::delete('/trips/{event}/settlement/expenses/{cost}', [PilotSettlementController::class, 'destroyExpense'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);
        Route::post('/trips/{event}/settlement/exchanges', [PilotSettlementController::class, 'storeExchange'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);
        Route::put('/trips/{event}/settlement/exchanges/{exchange}', [PilotSettlementController::class, 'updateExchange'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);
        Route::delete('/trips/{event}/settlement/exchanges/{exchange}', [PilotSettlementController::class, 'destroyExchange'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);
        Route::put('/trips/{event}/settlement/cash-return', [PilotSettlementController::class, 'updateCashReturn'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);
        Route::post('/trips/{event}/settlement/documents', [PilotSettlementController::class, 'storeDocument'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);
        Route::delete('/trips/{event}/settlement/documents/{document}', [PilotSettlementController::class, 'destroyDocument'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);
        Route::post('/trips/{event}/settlement/expenses/sync', [PilotSettlementController::class, 'syncExpenses'])
            ->middleware('api.ability:'.ApiAbilities::PILOT_WRITE);
    });

    Route::prefix('client')->middleware([
        'auth:sanctum',
        'api.ability:'.ApiAbilities::CLIENT_READ,
        'api.client',
        'throttle:60,1',
    ])->group(function () {
        Route::get('/trips', [ClientTripController::class, 'index']);
        Route::get('/trips/{event}', [ClientTripController::class, 'show']);
        Route::get('/trips/{event}/program', [ClientTripController::class, 'program']);

        Route::get('/trips/{event}/agreement', [ClientAgreementController::class, 'show']);
        Route::get('/trips/{event}/agreement/pdf', [ClientAgreementController::class, 'pdf']);

        Route::get('/trips/{event}/payments', [ClientPaymentsController::class, 'show']);
        Route::post('/trips/{event}/payments/installments/{schedule}/pay-link', [ClientPaymentsController::class, 'payLink'])
            ->middleware('api.ability:'.ApiAbilities::CLIENT_WRITE);

        Route::get('/trips/{event}/group-payments', [ClientGroupPaymentsController::class, 'show']);

        Route::get('/trips/{event}/participants', [ClientParticipantsController::class, 'index']);
        Route::post('/trips/{event}/participants', [ClientParticipantsController::class, 'store'])
            ->middleware('api.ability:'.ApiAbilities::CLIENT_WRITE);
        Route::put('/trips/{event}/participants/{participant}', [ClientParticipantsController::class, 'update'])
            ->middleware('api.ability:'.ApiAbilities::CLIENT_WRITE);
        Route::delete('/trips/{event}/participants/{participant}', [ClientParticipantsController::class, 'destroy'])
            ->middleware('api.ability:'.ApiAbilities::CLIENT_WRITE);
        Route::post('/trips/{event}/participants/{participant}/parent-link', [ClientParticipantsController::class, 'parentLink'])
            ->middleware('api.ability:'.ApiAbilities::CLIENT_WRITE);

        Route::get('/trips/{event}/extras', [ClientExtrasController::class, 'show']);
        Route::put('/trips/{event}/extras', [ClientExtrasController::class, 'update'])
            ->middleware('api.ability:'.ApiAbilities::CLIENT_WRITE);

        Route::get('/trips/{event}/invoice-requests', [ClientInvoiceRequestController::class, 'index']);
        Route::post('/trips/{event}/invoice-requests', [ClientInvoiceRequestController::class, 'store'])
            ->middleware('api.ability:'.ApiAbilities::CLIENT_WRITE);

        Route::get('/trips/{event}/inquiries', [ClientInquiryController::class, 'index']);
        Route::post('/trips/{event}/inquiries', [ClientInquiryController::class, 'store'])
            ->middleware('api.ability:'.ApiAbilities::CLIENT_WRITE);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/events', [EventController::class, 'index']);
        Route::get('/events/{event}', [EventController::class, 'show']);
        Route::get('/events/{event}/calculation', [EventController::class, 'calculation']);
        Route::post('/events/{event}/recalculate-price', [EventController::class, 'recalculatePrice']);
        Route::post('/events/{event}/program-points/reorder', [EventController::class, 'reorderProgramPoints']);

        Route::get('/tasks/board', [TaskController::class, 'board']);
        Route::post('/tasks/{task}/move', [TaskController::class, 'move']);

        Route::get('/notifications/counts', [NotificationController::class, 'counts']);
    });
});
