<x-mail::message>
# Potwierdzenie wniosku o fakturę

Dziękujemy — otrzymaliśmy Twój wniosek o fakturę za imprezę turystyczną.

**Nabywca:** {{ $invoiceRequest->company_name }}  
@if($invoiceRequest->nip)
**NIP:** {{ $invoiceRequest->nip }}  
@endif
**E-mail do faktury:** {{ $invoiceRequest->invoice_email }}  
@if($invoiceRequest->event)
**Impreza:** {{ $invoiceRequest->event->code }} — {{ $invoiceRequest->event->name }}  
@elseif($invoiceRequest->event_code_entered)
**Podany kod imprezy:** {{ $invoiceRequest->event_code_entered }}  
@endif
@if($invoiceRequest->amount)
**Kwota:** {{ number_format((float) $invoiceRequest->amount, 2, ',', ' ') }} PLN  
@endif

Fakturę (procedura marży dla biur podróży) wyślemy drogą elektroniczną po zakończeniu wyjazdu, na podany adres e-mail.

W razie pytań napisz na {{ config('mail.inquiries_to') ?: 'rafa@bprafa.pl' }}.

Dziękujemy,<br>
{{ config('app.name') }}
</x-mail::message>
