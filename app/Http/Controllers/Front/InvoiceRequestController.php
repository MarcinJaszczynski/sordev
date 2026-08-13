<?php

namespace App\Http\Controllers\Front;

use App\Actions\Finance\CreateClientInvoiceRequestAction;
use App\Data\CreateClientInvoiceRequestData;
use App\Models\ClientInvoiceRequest;
use App\Models\Event;
use App\Support\FrontFormProtection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceRequestController
{
    public function show(): View
    {
        return view('front.invoice-request', [
            'prefill' => [
                'buyer_type' => old('buyer_type', ClientInvoiceRequest::BUYER_COMPANY),
                'event_code' => old('event_code', request()->query('kod')),
            ],
        ]);
    }

    public function checkCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_code' => ['required', 'string', 'max:32'],
        ]);

        $event = $this->findEventByCode($data['event_code']);

        if (! $event) {
            return response()->json([
                'valid' => false,
                'message' => 'Nie znaleziono imprezy o podanym kodzie.',
            ]);
        }

        return response()->json([
            'valid' => true,
            'code' => $event->code,
            'name' => $event->name,
            'message' => $event->code.' — '.$event->name,
        ]);
    }

    public function store(Request $request, CreateClientInvoiceRequestAction $action): RedirectResponse
    {
        $ip = $request->ip();
        $ua = substr((string) $request->header('User-Agent'), 0, 191);
        $cacheKey = 'invoiceRequest:'.sha1($ip.'|'.$ua.'|'.($request->input('invoice_email') ?? ''));

        if (cache()->has($cacheKey)) {
            return back()->with('success', 'Dziękujemy! Jeśli przed chwilą już wysłałeś wniosek, poczekaj chwilę przed kolejnym.');
        }

        $buyerType = $request->input('buyer_type', ClientInvoiceRequest::BUYER_COMPANY);

        $data = $request->validate([
            'buyer_type' => ['required', 'in:person,company'],
            'company_name' => ['required', 'string', 'max:255'],
            'nip' => [
                $buyerType === ClientInvoiceRequest::BUYER_COMPANY ? 'required' : 'nullable',
                'string',
                'max:16',
            ],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:32'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:120'],
            'invoice_email' => ['required', 'email', 'max:255'],
            'applicant_phone' => ['nullable', 'string', 'max:50'],
            'event_code' => ['required', 'string', 'max:32'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:0'],
            'form_ts' => ['nullable', 'string', 'max:32'],
            'cf-turnstile-response' => ['nullable', 'string', 'max:2048'],
        ], [
            'company_name.required' => $buyerType === ClientInvoiceRequest::BUYER_PERSON
                ? 'Podaj imię i nazwisko.'
                : 'Podaj nazwę firmy / instytucji.',
            'nip.required' => 'Podaj NIP.',
            'invoice_email.required' => 'Podaj e-mail do faktury.',
            'event_code.required' => 'Podaj kod imprezy.',
        ]);

        if (FrontFormProtection::shouldSilentlyAccept($request, 'invoice_request_form')) {
            cache()->put($cacheKey, 1, now()->addSeconds(120));

            return back()->with('success', 'Dziękujemy! Twój wniosek został przyjęty.');
        }

        if (FrontFormProtection::turnstileBlocksSubmission($request, 'invoice_request_form')) {
            return back()
                ->withInput()
                ->withErrors([
                    'cf-turnstile-response' => 'Weryfikacja zabezpieczeń nie powiodła się (Cloudflare). Odśwież stronę i spróbuj ponownie.',
                ]);
        }

        if (FrontFormProtection::hasInvalidReferer($request)) {
            \Illuminate\Support\Facades\Log::notice('Invoice request invalid referer (accepted with warning)', [
                'ip' => $request->ip(),
                'referer' => $request->headers->get('referer'),
                'cf_ray' => $request->headers->get('CF-Ray'),
            ]);
        }

        $event = $this->findEventByCode($data['event_code']);
        if (! $event) {
            return back()
                ->withInput()
                ->withErrors(['event_code' => 'Nie znaleziono imprezy o kodzie „'.$data['event_code'].'”. Sprawdź kod z umowy / oferty.']);
        }

        try {
            $action(new CreateClientInvoiceRequestData(
                buyerType: $data['buyer_type'],
                companyName: $data['company_name'],
                invoiceEmail: $data['invoice_email'],
                source: ClientInvoiceRequest::SOURCE_WEB,
                nip: $data['nip'] ?? null,
                street: $data['street'] ?? null,
                houseNumber: $data['house_number'] ?? null,
                postalCode: $data['postal_code'] ?? null,
                city: $data['city'] ?? null,
                applicantPhone: $data['applicant_phone'] ?? null,
                amount: isset($data['amount']) && $data['amount'] !== null && $data['amount'] !== ''
                    ? (float) $data['amount']
                    : null,
                paymentReference: $data['payment_reference'] ?? null,
                notes: $data['notes'] ?? null,
                eventCodeEntered: strtoupper(trim($data['event_code'])),
                event: $event,
            ));
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors(['company_name' => 'Nie udało się zapisać wniosku. Spróbuj ponownie lub skontaktuj się z biurem.']);
        }

        cache()->put($cacheKey, 1, now()->addSeconds(120));

        return back()->with('success', 'Dziękujemy! Twój wniosek o fakturę został przyjęty. Potwierdzenie wysłaliśmy e-mailem.');
    }

    private function findEventByCode(string $code): ?Event
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', $code) ?? '');

        if ($normalized === '') {
            return null;
        }

        return Event::query()
            ->whereRaw('UPPER(code) = ?', [$normalized])
            ->first();
    }
}
