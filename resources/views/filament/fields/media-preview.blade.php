<div>
    @if(!empty($url))
        <div style="display:flex;align-items:center;gap:12px;">
            <img src="{{ $url }}" alt="preview" style="max-width:140px;max-height:140px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;" />
            <div style="display:flex;flex-direction:column;gap:6px;">
                <a href="{{ $url }}" target="_blank" class="text-primary-600">Otwórz w nowej karcie</a>
            </div>
        </div>
    @else
        <div class="text-gray-500">Brak podglądu</div>
    @endif
</div>
