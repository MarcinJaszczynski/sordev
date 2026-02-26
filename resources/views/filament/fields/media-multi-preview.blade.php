<div>
    @if(!empty($urls))
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;">
            @foreach($urls as $u)
                <div style="border:1px solid #e5e7eb;border-radius:6px;padding:4px;background:#fff;">
                    <img src="{{ $u }}" alt="preview" style="width:100%;height:100px;object-fit:cover;border-radius:4px;" />
                </div>
            @endforeach
        </div>
    @else
        <div class="text-gray-500">Brak wybranych pozycji</div>
    @endif
</div>
