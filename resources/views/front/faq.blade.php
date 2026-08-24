@extends('front.layout.master')

@section('head')
    @include('front.partials.seo', [
        'pageTitle' => 'FAQ – wycieczki szkolne i wyjazdy firmowe | Biuro Podróży RAFA',
        'pageDescription' => 'Odpowiedzi na najczęstsze pytania o wycieczki szkolne, wyjazdy firmowe, płatności, ubezpieczenia i rezerwacje w Biurze Podróży RAFA.',
    ])
    @if(isset($faqSchema))
        <x-seo.json-ld :schemas="[$faqSchema]" />
    @endif
@endsection

@section('main_content')
<main class="faq-area py-100">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <header class="section-title text-center mb-45">
                    <h1>Najczęstsze pytania (FAQ)</h1>
                    <p>Odpowiedzi na pytania o wycieczki szkolne, wyjazdy firmowe, płatności, ubezpieczenia i rezerwacje</p>
                </header>

                @forelse($groupedFaqs as $category => $entries)
                    <section class="mb-5" aria-labelledby="faq-cat-{{ $category }}">
                        <h2 id="faq-cat-{{ $category }}" class="h4 mb-3">{{ \App\Models\FaqEntry::categoryLabels()[$category] ?? $category }}</h2>
                        <x-seo.faq-section :faqs="$entries" :idPrefix="'faq-'.$category" />
                    </section>
                @empty
                    <p class="text-center text-muted">Brak opublikowanych pytań FAQ.</p>
                @endforelse
            </div>
        </div>
    </div>
</main>
@endsection
