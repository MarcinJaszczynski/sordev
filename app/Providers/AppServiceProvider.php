<?php

namespace App\Providers;

use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use App\Models\ClientInvoiceRequest;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventAgreementPaymentSchedule;
use App\Models\EventParticipantResignation;
use App\Models\EventSettlementCost;
use App\Models\Place;
use App\Models\VendorInvoice;
use App\Observers\ClientInvoiceRequestObserver;
use App\Observers\ContractPaymentScheduleObserver;
use App\Observers\EventAgreementPaymentScheduleObserver;
use App\Observers\EventObserver;
use App\Observers\EventParticipantResignationObserver;
use App\Observers\EventSettlementCostObserver;
use App\Observers\PlaceObserver;
use App\Observers\VendorInvoiceObserver;
use Illuminate\Support\Facades\Schema;
use App\Services\Tfg\HttpTfgFeedClient;
use App\Services\Tfg\MockTfgFeedClient;
use App\Services\Tfg\TfgFeedClientInterface;
use App\Support\FilamentFormBinding;
use App\Support\ViteAssetResolver;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TfgFeedClientInterface::class, function () {
            return config('tfg.driver') === 'http'
                ? app(HttpTfgFeedClient::class)
                : app(MockTfgFeedClient::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \FilamentTiptapEditor\TiptapEditor::macro('maxLength', function (int | \Closure | null $length): \FilamentTiptapEditor\TiptapEditor {
            $this->rules(fn (): array => filled($value = $this->evaluate($length)) ? ["max:{$value}"] : []);

            return $this;
        });

        \FilamentTiptapEditor\TiptapEditor::configureUsing(function (\FilamentTiptapEditor\TiptapEditor $editor) {
            $editor
                ->tools([
                    'undo', 'redo', '|',
                    'heading', 'bullet-list', 'ordered-list', 'blockquote', '|',
                    'bold', 'italic', 'strike', 'code', 'underline', 'color', 'highlight', 'link', '|',
                    'superscript', 'subscript', '|',
                    'align-left', 'align-center', 'align-right', 'align-justify',
                ])
                ->maxContentWidth('none')
                ->disableFloatingMenus()
                ->disableBubbleMenus()
                ->bubbleMenuTools(['bold', 'italic', 'underline', 'strike', 'color', 'highlight', 'link']);
        }, isImportant: false);

        FilamentFormBinding::apply();

        Vite::useBuildDirectory('vite-dist');

        FilamentAsset::register([
            Css::make('app-styles', ViteAssetResolver::asset('resources/css/app.css')),
            // Keep Filament JS stack isolated. Custom app.js is loaded on front layouts,
            // and injecting it globally into the panel can break table/select Alpine boot.
        ]);

        // Rejestracja komponenty Blade dla powiadomień
        $this->app['blade.compiler']->component('app.filament.components.topbar-notifications', 'app-filament-components-topbar-notifications');

        // Auto-generate place distance pairs on place create/update
        Place::observe(PlaceObserver::class);

        // Przelicz koszty i odśwież rozliczenie przy zmianach danych imprezy
        Event::observe(EventObserver::class);

        EventParticipantResignation::observe(EventParticipantResignationObserver::class);

        EventSettlementCost::observe(EventSettlementCostObserver::class);

        if (Schema::hasTable('contract_payment_schedules')) {
            ContractPaymentSchedule::observe(ContractPaymentScheduleObserver::class);
        }

        if (Schema::hasTable('event_agreement_payment_schedules')) {
            EventAgreementPaymentSchedule::observe(EventAgreementPaymentScheduleObserver::class);
        }

        if (Schema::hasTable('vendor_invoices')) {
            VendorInvoice::observe(VendorInvoiceObserver::class);
        }

        if (Schema::hasTable('client_invoice_requests')) {
            ClientInvoiceRequest::observe(ClientInvoiceRequestObserver::class);
        }

        // Backward-compatible alias: stara ścieżka komponentu po przeprowadzce klasy
        // (zapobiega ComponentNotFoundException dla starych tokenów Livewire w sesji użytkownika)
        Livewire::component(
            'app.filament.relation-managers.tasks-relation-manager',
            TasksRelationManager::class
        );

        // Po przeniesieniu aplikacji fallback na aktualny host eliminuje linki z 127.0.0.1/localhost.
        if (! $this->app->runningInConsole()) {
            $configuredPublicUrl = (string) config('app.public_url', config('app.url'));
            $configuredHost = (string) (parse_url($configuredPublicUrl, PHP_URL_HOST) ?? '');

            if ($configuredHost !== '' && $this->isLoopbackHost($configuredHost)) {
                $request = request();
                $requestRoot = rtrim($request->getSchemeAndHttpHost(), '/');

                URL::forceRootUrl($requestRoot);

                config([
                    'app.url' => $requestRoot,
                    'app.public_url' => $requestRoot,
                    'filesystems.disks.public.url' => $requestRoot.'/storage',
                ]);
            }
        }
    }

    private function isLoopbackHost(string $host): bool
    {
        $host = strtolower($host);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
