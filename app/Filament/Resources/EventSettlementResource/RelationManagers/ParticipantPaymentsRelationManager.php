<?php

namespace App\Filament\Resources\EventSettlementResource\RelationManagers;

use App\Filament\Resources\EventSettlementResource\Traits\DispatchesSettlementDataChanged;
use Filament\Resources\RelationManagers\RelationManager;

class ParticipantPaymentsRelationManager extends RelationManager
{
    use DispatchesSettlementDataChanged;

    protected static string $relationship = 'participantPayments';

    protected static ?string $title = 'Wpłaty uczestników';

    protected static ?string $recordTitleAttribute = 'participant_name';

    protected static string $view = 'filament.resources.event-settlement-resource.relation-managers.participant-payments-ledger';

    public ?int $focusPaymentId = null;
}
