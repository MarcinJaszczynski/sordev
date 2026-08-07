<?php

namespace App\Filament\Resources\EventResource\Concerns;

use Filament\Resources\Pages\Concerns\InteractsWithRecord;

/**
 * InteractsWithRecord + breadcrumbs Event (bez kolizji getBreadcrumbs).
 */
trait InteractsWithEventRecord
{
    use HasEventWorkflowContext;
    use InteractsWithRecord {
        HasEventWorkflowContext::getBreadcrumbs insteadof InteractsWithRecord;
    }
}
