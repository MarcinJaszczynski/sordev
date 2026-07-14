<?php
$content = file_get_contents('app/Support/Tasks/TaskContextRegistry.php');

if (strpos($content, 'urlForRecord') !== false) {
    echo "urlForRecord already exists.\n";
    exit(0);
}

$newMethod = <<<'PHP'
    public static function urlForRecord(?Model $record): ?string
    {
        if (! $record) {
            return null;
        }

        try {
            return match (true) {
                $record instanceof Event => \App\Filament\Resources\EventResource::getUrl('edit', ['record' => $record]),
                $record instanceof EventTemplate => \App\Filament\Resources\EventTemplateResource::getUrl('edit', ['record' => $record]),
                $record instanceof Contractor => \App\Filament\Resources\ContractorResource::getUrl('edit', ['record' => $record]),
                $record instanceof EventProgramPoint => $record->event_id ? \App\Filament\Resources\EventResource::getUrl('edit-program', ['record' => $record->event_id]) : null,
                $record instanceof EventTemplateProgramPoint => $record->event_template_id ? \App\Filament\Resources\EventTemplateResource::getUrl('edit', ['record' => $record->event_template_id]) : null,
                $record instanceof EventDocument => $record->event_id ? \App\Filament\Resources\EventResource::getUrl('documents', ['record' => $record->event_id]) : null,
                $record instanceof EventSettlementCost => $record->event_settlement_id ? \App\Filament\Resources\EventSettlementResource::getUrl('costs', ['record' => $record->event_settlement_id]) : null,
                $record instanceof EventSettlementDocument => $record->event_settlement_id ? \App\Filament\Resources\EventSettlementResource::getUrl('documents', ['record' => $record->event_settlement_id]) : null,
                $record instanceof EventSettlementParticipantPayment => $record->event_settlement_id ? \App\Filament\Resources\EventSettlementResource::getUrl('payments', ['record' => $record->event_settlement_id]) : null,
                $record instanceof PilotCashPreparation => $record->event_settlement_id ? \App\Filament\Resources\EventSettlementResource::getUrl('pilot-cash', ['record' => $record->event_settlement_id]) : null,
                default => null,
            };
        } catch (\Exception $e) {
            return null;
        }
    }

}
PHP;

$content = preg_replace('/}\s*$/', "\n$newMethod", $content);
file_put_contents('app/Support/Tasks/TaskContextRegistry.php', $content);
echo "urlForRecord added.\n";
