@props(['point'])

<div class="epp-flags" {{ $attributes }}>
    <button
        type="button"
        title="{{ $point->include_in_program ? 'W programie — kliknij, aby wyłączyć' : 'Poza programem — kliknij, aby włączyć' }}"
        wire:click="togglePointProperty({{ $point->id }}, 'include_in_program')"
        @class(['epp-flag', 'epp-flag--program', 'epp-flag--on' => $point->include_in_program, 'epp-flag--off' => ! $point->include_in_program])
    >Prog</button>
    <button
        type="button"
        title="{{ $point->include_in_calculation ? 'W kalkulacji — kliknij, aby wyłączyć' : 'Poza kalkulacją — kliknij, aby włączyć' }}"
        wire:click="togglePointProperty({{ $point->id }}, 'include_in_calculation')"
        @class(['epp-flag', 'epp-flag--calc', 'epp-flag--on' => $point->include_in_calculation, 'epp-flag--off' => ! $point->include_in_calculation])
    >Kalk</button>
    <button
        type="button"
        title="{{ $point->active ? 'Aktywny — kliknij, aby wyłączyć' : 'Nieaktywny — kliknij, aby włączyć' }}"
        wire:click="togglePointProperty({{ $point->id }}, 'active')"
        @class(['epp-flag', 'epp-flag--on' => $point->active, 'epp-flag--off' => ! $point->active])
    >Akt</button>
</div>
