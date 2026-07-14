<?php

use App\Http\Controllers\Admin\AgreementPdfDownloadController;
use App\Http\Controllers\Admin\BackupDownloadController;
use App\Http\Controllers\Admin\EventCalculationExportController;
use App\Http\Controllers\Admin\EventHotelOccupantsTemplateController;
use App\Http\Controllers\Admin\EventIndividualAgreementReportExportController;
use App\Http\Controllers\Admin\EventOfferWordController;
use App\Http\Controllers\Admin\EventParticipantInsuranceExportController;
use App\Http\Controllers\Admin\EventParticipantListTemplateController;
use App\Http\Controllers\Admin\EventInvoicePdfController;
use App\Http\Controllers\Admin\EventPrintPdfController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\EventCsvController;
use App\Http\Controllers\EventPriceDescriptionController;
use App\Http\Controllers\Front\AgreementFlowController;
use App\Http\Controllers\Front\FrontController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\Client\ClientContractPdfController;
use App\Http\Controllers\Pilot\PilotEventPdfController;
use App\Http\Middleware\EnsureApplicationNotInstalled;
use App\Livewire\PilotTripSettlementForm;
use App\Models\Conversation;
use App\Models\EventTemplate;
use App\Models\Place;
use App\Support\Region;
// === FRONTEND ROUTES (dodane z mergingSOR) ===
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', EnsureApplicationNotInstalled::class])
    ->prefix('install')
    ->name('install.')
    ->group(function () {
        Route::get('/', [InstallController::class, 'index'])->name('index');
        Route::get('/database', [InstallController::class, 'databaseForm'])->name('database');
        Route::post('/database', [InstallController::class, 'databaseStore'])->name('database.store');
        Route::get('/application', [InstallController::class, 'applicationForm'])->name('application');
        Route::post('/application', [InstallController::class, 'applicationStore'])->name('application.store');
        Route::get('/admin', [InstallController::class, 'adminForm'])->name('admin');
        Route::post('/admin', [InstallController::class, 'adminStore'])->name('admin.store');
    });

Route::middleware('web')->get('/install/complete', [InstallController::class, 'complete'])->name('install.complete');

// Redirect old root to canonical region root using cookie (handled by middleware later)
Route::get('/', function () {
    // Użyj helpera Region, aby domyślnie kierować do Warszawy gdy brak cookie
    $cookieId = request()->cookie('start_place_id');
    $slug = Region::slugForLinks($cookieId ? (int) $cookieId : null);

    return redirect()->route('home', ['regionSlug' => $slug]);
});

// Przechwyć wszystkie żądania, które zaczynają się od /region i przekieruj
// je do rzeczywistego sluga (np. /warszawa/...), zachowując resztę ścieżki i query string.
Route::any('/region/{any?}', function (Request $request, $any = '') {
    $slug = Region::slugForLinks(null);
    $path = trim($any, '/');
    $new = '/'.$slug.($path !== '' ? '/'.$path : '');
    $qs = $request->getQueryString();
    if ($qs) {
        $new .= '?'.$qs;
    }

    return redirect($new, 301);
})->where('any', '.*');

// Public login helper: keep legacy links working by pointing to Filament login screen
Route::get('/login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');

// Sitemap XML
Route::get('/sitemap.xml', function () {
    if (file_exists(public_path('sitemap.xml'))) {
        return response()->file(public_path('sitemap.xml'), [
            'Content-Type' => 'application/xml',
        ]);
    }

    return response('Sitemap not found', 404);
})->name('sitemap');

// Global blog routes (no region slug) for canonical blog URLs
Route::get('/blog', [FrontController::class, 'blog'])->name('blog.global');
Route::get('/blog/{slug}', [FrontController::class, 'blogPost'])->name('blog.post.global');
// Global documents routes (no region slug)
Route::get('/documents', [FrontController::class, 'documents'])->name('documents.global');
Route::get('/documents/{slug}', [FrontController::class, 'document'])->name('documents.post.global');

Route::get('/umowa/{token}', [AgreementFlowController::class, 'show'])->name('agreement.flow.show');
Route::post('/umowa/{token}/plan', [AgreementFlowController::class, 'confirmPlan'])->name('agreement.flow.plan');
Route::get('/umowa/{token}/zgody', [AgreementFlowController::class, 'consents'])->name('agreement.flow.consents');
Route::post('/umowa/{token}/zgody', [AgreementFlowController::class, 'storeConsents'])->name('agreement.flow.consents.store');
Route::get('/umowa/{token}/dane', [AgreementFlowController::class, 'personalData'])->name('agreement.flow.personal');
Route::post('/umowa/{token}/dane', [AgreementFlowController::class, 'storePersonalData'])->name('agreement.flow.personal.store');
Route::post('/umowa/{token}/zawrzyj', [AgreementFlowController::class, 'sign'])->name('agreement.flow.sign');
Route::get('/umowa/{token}/platnosc', [AgreementFlowController::class, 'payment'])->name('agreement.flow.payment');
Route::post('/umowa/{token}/platnosc', [AgreementFlowController::class, 'pay'])->name('agreement.flow.pay');
Route::get('/umowa/{token}/potwierdzenie', [AgreementFlowController::class, 'success'])->name('agreement.flow.success');

Route::group(['prefix' => '{regionSlug}', 'where' => ['regionSlug' => '[A-Za-z0-9\-]+']], function () {
    Route::post('/send-email', [FrontController::class, 'sendEmail'])->middleware('throttle:5,1')->name('send-email');
    Route::get('/', [FrontController::class, 'home'])->name('home');
    Route::get('/directory-packages', [FrontController::class, 'directorypackages'])->name('directory-packages');
    // regional blog routes removed: use global routes '/blog' and '/blog/{slug}' instead
    // public-facing packages are now under '/oferty' (Polish). Keep the route name 'packages'
    // so all existing calls to route('packages') keep working. Add a redirect from the old
    // '/packages' path to the new '/oferty' for backward compatibility.
    Route::get('/oferty', [FrontController::class, 'packages'])->name('packages');
    Route::get('/packages', function ($regionSlug) {
        return redirect()->route('packages', ['regionSlug' => $regionSlug]);
    });
    Route::get('/packages/partial', [FrontController::class, 'packagesPartial'])->name('packages.partial');
    Route::get('/package/{slug}', [FrontController::class, 'package'])->name('package');
    // Pretty package route remains the same pattern but now nested (duplicated regionSlug) -> keep original outside group
    Route::get('/insurance', [FrontController::class, 'insurance'])->name('insurance');
    Route::get('/documents', function ($regionSlug) {
        return redirect()->route('documents.global');
    })->name('documents');
    Route::get('/contact', [FrontController::class, 'contact'])->name('contact');
    Route::get('/faq', [FrontController::class, 'faq'])->name('faq');
});

// SEO friendly pretty package route stays global to avoid double region slug
// Pretty package route (unicode-aware slug). Allow diacritics in slug using \pL (letters) + digits + hyphen.
Route::get('/{regionSlug}/{dayLength}/{id}/{slug}', [FrontController::class, 'packagePretty'])
    ->where([
        'regionSlug' => '[A-Za-z0-9\-]+', // pozostawiamy region jako ascii slug (pochodzi z Place::name slug)
        'dayLength' => '[0-9]+-dniowe',
        'id' => '[0-9]+',
        'slug' => '[\pL0-9\-]+', // wymaga trybu unicode w PCRE, Laravel domyślnie używa 'u'
    ])
    ->name('package.pretty');

Route::post('/{regionSlug}/{dayLength}/{id}/{slug}/word', [FrontController::class, 'packagePrettyWord'])
    ->where([
        'regionSlug' => '[A-Za-z0-9\-]+',
        'dayLength' => '[0-9]+-dniowe',
        'id' => '[0-9]+',
        'slug' => '[\pL0-9\-]+',
    ])
    ->middleware('auth')
    ->name('package.pretty.word');

// Import/eksport CSV dla Eventów
Route::get('/events/export-csv', [EventCsvController::class, 'export'])->name('events.export.csv');
Route::post('/events/import-csv', [EventCsvController::class, 'import'])->name('events.import.csv');

Route::get('/test-log', function () {
    Log::info('Test route accessed at '.now());

    return 'Test log written - check storage/logs/laravel.log';
});

// Local-only: quick email test endpoint
Route::get('/test-mail', function () {
    if (! config('app.debug')) {
        abort(404);
    }
    try {
        \Illuminate\Support\Facades\Mail::raw('Test message from /test-mail at '.now(), function ($m) {
            $m->to(config('mail.inquiries_to') ?: (app()->environment('production') ? 'rafa@bprafa.pl' : 'm.jasczynski@gmail.com'))
                ->subject('Postmark/Mailer smoke test');
        });

        return 'Mail dispatched using mailer: '.config('mail.default');
    } catch (\Throwable $e) {
        return response('Mail failed: '.$e->getMessage(), 500);
    }
});

Route::get('/test-drag-drop', function () {
    Log::info('Testing drag & drop functionality');

    try {
        // Znajdź pierwszy event template
        $eventTemplate = EventTemplate::first();
        if (! $eventTemplate) {
            return 'No event template found';
        }

        // Utwórz instancję komponentu
        $kanban = new \App\Filament\Resources\EventTemplateResource\Widgets\EventProgramKanban;
        $kanban->record = $eventTemplate;

        // Sprawdź, czy są jakieś punkty programu
        $pivotRecords = \Illuminate\Support\Facades\DB::table('event_template_event_template_program_point')
            ->where('event_template_id', $eventTemplate->id)
            ->get();

        if ($pivotRecords->isEmpty()) {
            return 'No program points found for event template';
        }

        // Testuj movePoint z pierwszym rekordem
        $firstRecord = $pivotRecords->first();
        $kanban->movePoint($firstRecord->id, 2, [$firstRecord->id]);

        return 'Test completed - check logs';
    } catch (\Exception $e) {
        Log::error('Test drag & drop error: '.$e->getMessage());

        return 'Error: '.$e->getMessage();
    }
});

Route::get('/check-data', function () {
    try {
        $eventTemplate = EventTemplate::first();
        if (! $eventTemplate) {
            return response()->json(['error' => 'No event template found']);
        }

        $pivotRecords = \Illuminate\Support\Facades\DB::table('event_template_event_template_program_point')
            ->where('event_template_id', $eventTemplate->id)
            ->get();

        $programPoints = $eventTemplate->programPoints()->withPivot(['day', 'order_number'])->get();

        $data = $programPoints->map(function ($point) {
            return [
                'id' => $point->id,
                'pivot_id' => $point->pivot->id,
                'name' => $point->name,
                'day' => $point->pivot->day,
                'order_number' => $point->pivot->order_number,
            ];
        });

        return response()->json([
            'event_template' => $eventTemplate->name,
            'program_points' => $data,
            'pivot_records_count' => $pivotRecords->count(),
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

Route::get('/auto-login', function () {
    try {
        // Sprawdź czy użytkownik testowy istnieje
        $user = \App\Models\User::where('email', 'admin@test.com')->first();
        if (! $user) {
            return 'User not found. Please run: php artisan make:test-user';
        }

        // Zaloguj użytkownika
        Auth::login($user, true);

        // Przekieruj do panelu admina
        return redirect('/admin');
    } catch (\Exception $e) {
        return 'Error: '.$e->getMessage();
    }
});

// Portal pilota — rozliczenie mobilne i PDF teczki
Route::middleware(['auth', 'web'])->prefix('pilot')->group(function () {
    Route::get('/trip/{event}/settle', PilotTripSettlementForm::class)
        ->name('pilot.trip.settle');
    Route::get('/events/{event}/pdf/{audience}', [PilotEventPdfController::class, 'download'])
        ->where('audience', 'pilot|folder')
        ->name('pilot.events.pdf');
});

// Portal klienta — PDF umowy
Route::middleware(['auth', 'web'])->prefix('portal')->group(function () {
    Route::get('/events/{event}/contract-pdf', ClientContractPdfController::class)
        ->name('portal.contract.pdf');
});

// Admin notifications API endpoint
Route::middleware(['auth', 'web'])->prefix('admin')->group(function () {
    Route::get('/notifications/counts', [NotificationController::class, 'getCounts'])->name('admin.notifications.counts');
    Route::post('/notifications/mark-read', [NotificationController::class, 'markRead'])->name('admin.notifications.mark-read');
    Route::get('/contracts/{contract}/agreement-pdf', AgreementPdfDownloadController::class)
        ->name('admin.contracts.agreement-pdf');
    Route::get('/events/{event}/pdf/{audience}', [EventPrintPdfController::class, 'download'])
        ->where('audience', 'pilot|hotel|driver|folder|all|program_with_times|program_without_times')
        ->name('admin.events.pdf');
    Route::get('/events/{event}/invoices/pdf', [EventInvoicePdfController::class, 'download'])
        ->name('admin.events.invoices.pdf');
    Route::get('/events/{event}/calculation/pdf', [EventCalculationExportController::class, 'pdf'])
        ->name('admin.events.calculation.pdf');
    Route::get('/events/{event}/calculation/excel', [EventCalculationExportController::class, 'excel'])
        ->name('admin.events.calculation.excel');
    Route::get('/events/{event}/hotel-occupants/import-template/{format?}', EventHotelOccupantsTemplateController::class)
        ->where('format', 'csv|xlsx')
        ->defaults('format', 'xlsx')
        ->name('admin.events.hotel-occupants.import-template');
    Route::get('/events/{event}/participants/import-template/{format?}', EventParticipantListTemplateController::class)
        ->where('format', 'csv|xlsx')
        ->defaults('format', 'csv')
        ->name('admin.events.participants.import-template');
    Route::get('/events/{event}/participants/insurance-export/{format?}', EventParticipantInsuranceExportController::class)
        ->where('format', 'csv|xlsx')
        ->defaults('format', 'xlsx')
        ->name('admin.events.participants.insurance-export');
    Route::get('/events/{event}/individual-agreements-export/{format}', EventIndividualAgreementReportExportController::class)
        ->where('format', 'csv|xlsx')
        ->name('admin.events.individual-agreements.export');
    Route::get('/events/{event}/offer/word', EventOfferWordController::class)
        ->name('admin.events.offer.word');
    Route::get('/task-attachments/{attachment}/download', \App\Http\Controllers\Admin\TaskAttachmentDownloadController::class)
        ->name('admin.task-attachments.download');
    Route::get('/backups/{filename}/download', [BackupDownloadController::class, 'download'])
        ->name('admin.backups.download');
    Route::get('/tfg-feed-logs/{feedLog}/download', \App\Http\Controllers\Admin\TfgFeedLogDownloadController::class)
        ->name('tfg.feed-log.download');
    Route::get('/tfg-csv-exports/{feedLog}/download', \App\Http\Controllers\Admin\TfgCsvExportDownloadController::class)
        ->name('tfg.csv-export.download');
    Route::post('/sitemap/generate', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('sitemap:generate');

            return back()->with('success', 'Sitemap wygenerowana pomyślnie!');
        } catch (\Exception $e) {
            return back()->with('error', 'Błąd: '.$e->getMessage());
        }
    })->name('sitemap.generate');
});

Route::get('/admin/conversations/open', function () {
    $user = Auth::user();
    if (! $user) {
        return redirect('/login');
    }
    // Najpierw nieprzeczytana, potem najnowsza
    $conversation = Conversation::whereHas('participants', function ($q) use ($user) {
        $q->where('user_id', $user->id);
    })
        ->with(['participants', 'messages'])
        ->get()
        ->sortByDesc(fn ($c) => $c->unreadCount($user))
        ->sortByDesc('last_message_at')
        ->first();
    if ($conversation) {
        return redirect('/admin/chat?conversation='.$conversation->id);
    }

    return redirect('/admin/chat');
});

// Event price description routes
Route::get('/event/{eventId}/price-description', [EventPriceDescriptionController::class, 'show'])->name('event.price-description.show');
Route::get('/event/{eventId}/price-description/edit', [EventPriceDescriptionController::class, 'edit'])->name('event.price-description.edit');
Route::post('/event/{eventId}/price-description/update', [EventPriceDescriptionController::class, 'update'])->name('event.price-description.update');
