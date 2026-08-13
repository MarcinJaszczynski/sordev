<x-mail::message>
# Nowy wniosek o fakturę

Wpłynął wniosek o fakturę za imprezę turystyczną.

**Źródło:** {{ $invoiceRequest->source_label }}  
**Typ nabywcy:** {{ $invoiceRequest->buyer_type_label }}  
**Nabywca:** {{ $invoiceRequest->company_name }}  
@if($invoiceRequest->nip)
**NIP:** {{ $invoiceRequest->nip }}  
@endif
**Adres:** {{ $invoiceRequest->full_address ?: '—' }}  
**E-mail:** {{ $invoiceRequest->invoice_email }}  
@if($invoiceRequest->applicant_phone)
**Telefon:** {{ $invoiceRequest->applicant_phone }}  
@endif
@if($invoiceRequest->event)
**Impreza:** {{ $invoiceRequest->event->code }} — {{ $invoiceRequest->event->name }}  
@elseif($invoiceRequest->event_code_entered)
**Kod wpisany przez klienta:** {{ $invoiceRequest->event_code_entered }} (brak powiązania)  
@else
**Impreza:** niepowiązana  
@endif
@if($invoiceRequest->amount)
**Kwota:** {{ number_format((float) $invoiceRequest->amount, 2, ',', ' ') }} PLN  
@endif
@if($invoiceRequest->payment_reference)
**Referencja:** {{ $invoiceRequest->payment_reference }}  
@endif
@if($invoiceRequest->notes)
**Uwagi:** {{ $invoiceRequest->notes }}  
@endif

<x-mail::button :url="$inboxUrl">
Otwórz skrzynkę wniosków
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
