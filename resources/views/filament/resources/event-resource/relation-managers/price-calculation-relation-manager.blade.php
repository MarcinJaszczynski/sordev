<div class="fi-resource-relation-manager flex flex-col gap-y-6">
    <div class="mb-8">
        <h4 class="text-md mb-4 font-semibold">Szczegółowa kalkulacja kosztów</h4>
        @include('partials.event-calculation-explanation')

        <x-event-price-calculation
            :template="$template"
            :participant-count="$participantCount"
            :gratis-count="$gratisCount"
            :start-place-id="$startPlaceId"
            :calculated-total="$calculatedTotal"
        />
    </div>
</div>
