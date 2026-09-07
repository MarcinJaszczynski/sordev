@extends('front.layout.master')

@section('head')
    @include('front.partials.seo', ($guideMode ?? false) ? [
        'pageTitle' => 'Poradnik | Wycieczki szkolne i wyjazdy firmowe – Biuro Podróży RAFA',
        'pageDescription' => 'Praktyczne poradniki dla szkół i firm: organizacja wycieczki szkolnej, wyjazdu integracyjnego, ubezpieczenia, dokumenty i przygotowanie grupy krok po kroku.',
        'canonical' => route('guide.global'),
    ] : [])
@endsection

@section('main_content')
@php
    $isGuide = $guideMode ?? false;
    $listRoute = $isGuide ? route('guide.global') : route('blog.global');
    $pageHeading = $isGuide ? 'Poradnik turystyczny' : 'Aktualności';
    $pageLead = $isGuide
        ? 'Praktyczne wskazówki dla nauczycieli, organizatorów i firm planujących wyjazd grupowy.'
        : 'Najnowsze wpisy, porady i aktualizacje z naszej działalności.';
    $breadcrumbLabel = $isGuide ? 'Poradnik' : 'Aktualności';
@endphp
<!-- BLOG PAGE RENDER: {{ now()->format('Y-m-d H:i:s') }} | Posts: {{ isset($posts) ? $posts->count() : 0 }} -->

<div class="page-top">
    <div class="container">
        <div class="breadcrumb-container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Start</a></li>
                <li class="breadcrumb-item active">{{ $breadcrumbLabel }}</li>
            </ol>
        </div>
    </div>
</div>

<div class="blog-sections" data-v="20251002-1310">
    <div class="top-section">
        <div class="box-for-picture pb_10">
            <div class="insurance-picture" style="background-image: linear-gradient(to left, rgba(0,0,0,0.45) 20%, rgba(0,0,0,0.08) 60%), url({{ asset('storage/dokumenty.jpg') }});">
                <div class="insurance-picture-space">
                    <div class="insurance-text-ad">
                        <p class="big">{{ $pageHeading }}</p>
                        <p class="small hide-mobile">{{ $pageLead }}</p>
                        <p class="sub">Wszystko w jednym miejscu.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Search and filters --}}
    <div class="container pt_40">
        <div class="row justify-content-center mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="get" action="{{ $listRoute }}" class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Szukaj w artykułach</label>
                                <input type="search" name="q" value="{{ $search ?? request('q') }}" class="form-control" placeholder="Wpisz szukane słowo...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">Sortowanie</label>
                                <select name="sort" class="form-select">
                                    <option value="newest" {{ (isset($sort) && $sort === 'newest') || !isset($sort) ? 'selected' : '' }}>Najnowsze</option>
                                    <option value="oldest" {{ isset($sort) && $sort === 'oldest' ? 'selected' : '' }}>Najstarsze</option>
                                    <option value="title_asc" {{ isset($sort) && $sort === 'title_asc' ? 'selected' : '' }}>Tytuł A→Z</option>
                                    <option value="title_desc" {{ isset($sort) && $sort === 'title_desc' ? 'selected' : '' }}>Tytuł Z→A</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary w-100" type="submit">Szukaj</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Masonry grid of posts --}}
        @php
            $guideCategoryLabels = [
                'wycieczki-szkolne' => 'Wycieczki szkolne',
                'wyjazdy-firmowe' => 'Wyjazdy firmowe',
                'bezpieczenstwo-i-ubezpieczenia' => 'Ubezpieczenia',
                'organizacja-grupy' => 'Organizacja',
                'transport' => 'Transport',
            ];
        @endphp
        <div class="row">
            <div class="col-12">
                @if(isset($posts) && $posts->count() > 0)
                    <div class="masonry-grid">
                        @foreach($posts as $post)
                            @php
                                $categoryLabel = $guideCategoryLabels[$post->guide_category ?? ''] ?? null;
                            @endphp
                            <div class="masonry-item">
                                {{-- Uwaga: nie używamy klasy h-100 — w style.css frontu jest .h-100 { height: 100px !important } --}}
                                <article class="card blog-card border-0 shadow-sm">
                                    <a href="{{ route('blog.post.global', $post->slug) }}" class="blog-card-link" aria-label="Przejdź do wpisu: {{ $post->title }}">
                                        <div class="blog-card-image {{ $post->featured_image ? '' : 'blog-card-image--placeholder' }}">
                                            @if($post->featured_image)
                                                <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->featured_image_alt ?: $post->title }}" class="blog-card-img" loading="lazy" onerror="this.closest('.blog-card-image').classList.add('blog-card-image--placeholder','no-image'); this.remove();">
                                            @endif
                                        </div>
                                    </a>
                                    <div class="card-body p-3">
                                        @if($categoryLabel)
                                            <div class="blog-card-category mb-2">{{ $categoryLabel }}</div>
                                        @elseif($isGuide)
                                            <div class="blog-card-category mb-2">Poradnik</div>
                                        @endif
                                        <h2 class="card-title blog-card-heading mb-2">
                                            <a href="{{ route('blog.post.global', $post->slug) }}" class="blog-title-link">{{ $post->title }}</a>
                                        </h2>
                                        <p class="card-text text-muted small mb-3">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 120) }}</p>
                                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                            <small class="text-muted">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                {{ $post->published_at ? $post->published_at->format('d.m.Y') : $post->created_at->format('d.m.Y') }}
                                            </small>
                                            <a href="{{ route('blog.post.global', $post->slug) }}" class="btn btn-primary btn-sm">Czytaj</a>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        Brak wpisów pasujących do zapytania.
                    </div>
                @endif
            </div>
        </div>

        {{-- Pagination --}}
        @if(isset($posts) && $posts->hasPages())
            <div class="row mt-4">
                <div class="col-12 d-flex justify-content-center">
                    {{ $posts->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

<style>
    /* Banner styling (same as insurance/documents) - narrower */
    .blog-sections .box-for-picture { max-width: 100%; }
    .blog-sections .insurance-picture {
        border-radius: 22px;
        background-size: cover;
        background-position: center;
        padding: 72px 48px;
        min-height: 320px;
        display: flex;
        align-items: center;
    }
    .blog-sections .insurance-picture-space {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }
    .blog-sections .insurance-text-ad {
        color: #ffffff;
        text-align: left;
        margin-left: auto;
        font-weight: 700;
        font-size: clamp(1.5rem, 2.6vw, 2.2rem);
        line-height: 1.6;
        max-width: 48%;
        display: block;
        background: rgba(0,0,0,0.32);
        padding: 20px 26px;
        border-radius: 12px;
        box-shadow: 0 14px 40px rgba(0,0,0,0.28);
        text-shadow: 0 6px 18px rgba(0,0,0,0.45);
    }
    .blog-sections .insurance-text-ad p { margin: 0 0 10px 0; }
    .blog-sections .insurance-text-ad p.big { font-size: 1.45rem; font-weight:700; }
    .blog-sections .insurance-text-ad p.small { font-size: 1.02rem; font-weight:600; opacity:0.95; }
    .blog-sections .insurance-text-ad p.sub { font-size: 0.98rem; font-weight:600; opacity:0.95; }
    .blog-sections .insurance-text-ad .hide-mobile { display: block; font-weight: 600; font-size: 1.05rem; opacity: 0.95; }

    /* Container padding to prevent footer overlap */
    .blog-sections .container { padding-bottom: 120px; }

    /* Masonry Grid (Pinterest style) */
    .masonry-grid {
        column-count: 4;
        column-gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .masonry-item {
        break-inside: avoid;
        margin-bottom: 1.5rem;
    }
    
    .blog-card-link {
        display: block;
        text-decoration: none;
    }

    .blog-title-link {
        color: inherit;
        text-decoration: none;
    }

    .blog-title-link:hover {
        text-decoration: underline;
    }

    .blog-card {
        height: auto !important; /* chroni przed globalnym .h-100 { height: 100px } */
        transition: transform 0.2s, box-shadow 0.2s;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        background: #fff;
    }
    
    .blog-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.15) !important;
    }
    
    .blog-card-image {
        height: 160px;
        overflow: hidden;
        background: #eef2f6;
    }
    
    .blog-card-image .blog-card-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.3s;
    }
    
    .blog-card:hover .blog-card-image .blog-card-img {
        transform: scale(1.05);
    }

    .blog-card-image--placeholder,
    .blog-card-image.no-image {
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(145deg, #d9e6f2 0%, #eef4f9 55%, #f7fafc 100%);
    }

    .blog-card-placeholder-label {
        font-size: 0.85rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: #3a5168;
        text-transform: uppercase;
        padding: 8px 12px;
        border: 1px solid rgba(58, 81, 104, 0.18);
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.65);
    }

    .blog-card-category {
        display: inline-block;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #2f6fed;
    }

    .blog-card .card-body {
        flex: 1 1 auto;
        background: #fff;
    }
    
    .blog-card-heading,
    .blog-card .card-title {
        font-size: 1.05rem;
        font-weight: 700;
        line-height: 1.35;
        color: #1f2a37;
        margin: 0;
    }

    /* Responsive */
    @media (max-width: 1199.98px) {
        .masonry-grid {
            column-count: 3;
        }
        
        .blog-card-image {
            height: 150px;
        }
    }
    
    @media (max-width: 991.98px) {
        .masonry-grid {
            column-count: 2;
        }
        
        .blog-sections .insurance-picture {
            padding: 40px 28px;
            min-height: 200px;
        }
        
        .blog-sections .insurance-text-ad {
            max-width: 60%;
        }
        
        .blog-card-image {
            height: 140px;
        }
    }
    
    @media (max-width: 767.98px) {
        .masonry-grid {
            column-count: 1;
        }
        
        .blog-sections .insurance-picture {
            padding: 28px 20px;
            min-height: 140px;
        }
        
        .blog-sections .insurance-text-ad { 
            max-width: 100%; 
            font-size: 1.2rem; 
        }
        
        .blog-sections .insurance-picture-space { 
            justify-content: center; 
        }
        
        /* hide blog banner on mobile */
        .blog-sections .top-section { 
            display: none; 
        }
        
        .blog-card-image {
            height: 130px;
        }
        
        .blog-sections .container {
            padding-bottom: 60px;
        }
    }
</style>

@endsection
