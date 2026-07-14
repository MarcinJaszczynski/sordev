@props(['point'])

<div class="epp-flags" {{ $attributes }}>
    <button
        type="button"
        title="W programie"
        wire:click="togglePointProperty({{ $point->id }}, 'include_in_program')"
        @class(['epp-flag', 'epp-flag--on' => $point->include_in_program, 'epp-flag--off' => ! $point->include_in_program])
    >P</button>
    <button
        type="button"
        title="W kalkulacji"
        wire:click="togglePointProperty({{ $point->id }}, 'include_in_calculation')"
        @class(['epp-flag', 'epp-flag--on' => $point->include_in_calculation, 'epp-flag--off' => ! $point->include_in_calculation])
    >K</button>
    <button
        type="button"
        title="Aktywny"
        wire:click="togglePointProperty({{ $point->id }}, 'active')"
        @class(['epp-flag', 'epp-flag--on' => $point->active, 'epp-flag--off' => ! $point->active])
    >A</button>
</div>
