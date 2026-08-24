@props([
    'faqs' => collect(),
    'title' => null,
    'idPrefix' => 'faq',
])

@if($faqs->isNotEmpty())
    <section class="faq-area {{ $attributes->get('class') }}" aria-labelledby="{{ $idPrefix }}-heading">
        @if($title)
            <div class="section-title text-center mb-45">
                <h2 id="{{ $idPrefix }}-heading">{{ $title }}</h2>
            </div>
        @endif

        <div class="faq-accordion">
            @foreach($faqs as $index => $faq)
                @php $targetId = $idPrefix.'-'.$faq->id; @endphp
                <div class="faq-item">
                    <button class="faq-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $targetId }}" aria-expanded="false" aria-controls="{{ $targetId }}">
                        <span class="faq-question">{{ $faq->question }}</span>
                        <span class="faq-icon" aria-hidden="true"><i class="fas fa-plus"></i></span>
                    </button>
                    <div id="{{ $targetId }}" class="collapse" data-bs-parent="#{{ $idPrefix }}-accordion">
                        <div class="faq-answer">
                            {!! $faq->answer !!}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

<style>
.faq-area { padding: 20px 0; }
.faq-item { margin-bottom: 15px; background: white; border-radius: 8px; border: 1px solid #e0e0e0; overflow: hidden; }
.faq-button { width: 100%; padding: 20px; background: white; border: none; text-align: left; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-size: 16px; font-weight: 500; color: #333; }
.faq-button:hover { background: #f0f0f0; }
.faq-question { flex: 1; }
.faq-icon { margin-left: 15px; color: #0066cc; font-size: 18px; }
.faq-answer { padding: 0 20px 20px; color: #666; line-height: 1.6; }
.faq-answer a { color: #0066cc; }
</style>
