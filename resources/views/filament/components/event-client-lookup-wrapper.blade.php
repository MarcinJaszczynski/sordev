@php
    /** @var array<string, mixed>|null $initialSelected */
    $initialSelected = $initialSelected ?? null;
    /** @var array<int, array<string, mixed>> $initialAdditional */
    $initialAdditional = $initialAdditional ?? [];
    $primaryContractorId = $primaryContractorId ?? null;
    $wireKey = $wireKey ?? 'event-client-lookup';
    $additionalWireKey = $additionalWireKey ?? ($wireKey.'-additional');
@endphp

<div class="space-y-4">
    @livewire('event-client-lookup', [
        'selected' => $initialSelected,
    ], key($wireKey))

    @livewire('event-additional-ordering-parties-lookup', [
        'additional' => $initialAdditional,
        'primaryContractorId' => $primaryContractorId,
    ], key($additionalWireKey))
</div>
