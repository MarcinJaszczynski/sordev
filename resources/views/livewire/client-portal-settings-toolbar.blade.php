<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        {{ $this->inviteParticipantAction }}
        {{ $this->inviteGuardianAction }}
        {{ $this->revokeAccessAction }}
        <a href="{{ $this->previewUrl() }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Podgląd hubu imprezy
        </a>
    </div>

    @if($accesses->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Użytkownik</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Rola</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Źródło</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Udostępniono</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600">Podgląd</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($accesses as $access)
                        <tr>
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900">{{ $access->user?->name ?: '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $access->user?->email ?: '—' }}</div>
                            </td>
                            <td class="px-3 py-2">{{ $access->role === 'guardian' ? 'Opiekun' : 'Uczestnik' }}</td>
                            <td class="px-3 py-2">{{ $access->source === 'agreement_flow' ? 'Flow umowy' : 'Biuro' }}</td>
                            <td class="px-3 py-2">{{ optional($access->shared_at)->format('d.m.Y H:i') ?: '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ $this->previewAsAccessUrl($access) }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center rounded-md bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-blue-700">
                                    Podgląd jako {{ $access->role === 'guardian' ? 'opiekun' : 'uczestnik' }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">Brak aktywnych dostępów do portalu klienta. Zaproś uczestnika lub opiekuna, żeby móc podglądać ich ekran.</p>
    @endif
</div>
