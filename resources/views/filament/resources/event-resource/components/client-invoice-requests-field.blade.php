@php
    use App\Models\ClientInvoiceRequest;

    $requests = ClientInvoiceRequest::query()
        ->with('user')
        ->where('event_id', $this->record->getKey())
        ->orderByDesc('created_at')
        ->limit(50)
        ->get();
@endphp

@if($requests->isEmpty())
    <p class="text-sm text-gray-500">Brak wniosków o fakturę dla tej imprezy.</p>
@else
    <div class="overflow-hidden rounded-xl border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Data</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Firma</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">NIP</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">E-mail</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach($requests as $request)
                    <tr>
                        <td class="px-3 py-2">{{ $request->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-3 py-2">{{ $request->company_name }}</td>
                        <td class="px-3 py-2">{{ $request->nip }}</td>
                        <td class="px-3 py-2">{{ $request->invoice_email }}</td>
                        <td class="px-3 py-2">{{ $request->status_label }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
