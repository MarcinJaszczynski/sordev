<?php

namespace App\Providers;

use App\Models\Bus;
use App\Models\Markup;
use App\Models\Vehicle;
use App\Policies\BusPolicy;
use App\Policies\MarkupPolicy;
use App\Policies\VehiclePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Bus::class => BusPolicy::class,
        Vehicle::class => VehiclePolicy::class,
        Markup::class => MarkupPolicy::class,
        \App\Models\Event::class => \App\Policies\EventPolicy::class,
        \App\Models\EventSettlement::class => \App\Policies\EventSettlementPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
