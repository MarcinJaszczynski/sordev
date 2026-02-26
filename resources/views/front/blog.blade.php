@extends('front.layout.master')

@section('head')
    @include('front.partials.seo')
@endsection

@section('main_content')
<!-- BLOG PAGE RENDER: {{ now()->format('Y-m-d H:i:s') }} | Posts: {{ isset($posts) ? $posts->count() : 0 }} -->

<div class="page-top">
    <div class="container">
        <div class="breadcrumb-container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Start</a></li>
                <li class="breadcrumb-item active">Aktualności</li>
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
                        <p class="big">Aktualności</p>
                        <p class="small hide-mobile">Najnowsze wpisy, porady i aktualizacje z naszej działalności.</p>
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
                        <form method="get" action="{{ route('blog.global') }}" class="row g-3 align-items-end">
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
        <div class="row">
            <div class="col-12">
                @if(isset($posts) && $posts->count() > 0)
                    <div class="masonry-grid">
                        @foreach($posts as $post)
                            <div class="masonry-item">
                                <div class="card blog-card border-0 shadow-sm h-100 position-relative">
                                    <div class="blog-card-image">
                                        @if($post->featured_image)
                                            <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}" class="w-100 h-100" loading="lazy" onerror="this.closest('.blog-card-image').classList.add('no-image'); this.remove();">
                                        @else
                                            <img src="{{ asset('uploads/blog-placeholder.jpg') }}" alt="Brak zdjęcia" class="w-100 h-100" loading="lazy">
                                        @endif
                                    </div>
                                    <div class="card-body p-3">
                                        <h5 class="card-title mb-2">{{ $post->title }}</h5>
                                        <p class="card-text text-muted small mb-3">{{ $post->excerpt ?: Str::limit(strip_tags($post->content), 120) }}</p>
                                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                            <small class="text-muted">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                {{ $post->published_at ? $post->published_at->format('d.m.Y') : $post->created_at->format('d.m.Y') }}
                                            </small>
                                            <span class="btn btn-primary btn-sm">Czytaj</span>
                                        </div>
                                        <a href="{{ route('blog.post.global', $post->slug) }}" class="card-link-overlay" aria-label="Przejdź do wpisu: {{ $post->title }}"></a>
                                    </div>
                                </div>
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
    
    .blog-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .blog-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.15) !important;
    }
    
    /* Square images */
    .blog-card-image {
        height: 220px; /* square */
        overflow: hidden;
        background: #f8f9fa;
    }
    
    .blog-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.3s;
    }
    
    .blog-card:hover .blog-card-image img {
        transform: scale(1.05);
    }
    /* ensure stretched-link doesn't cover the image anchor (we already link the image) */
    .blog-card .stretched-link { z-index: 1; }
    .blog-card-image a { position: relative; z-index: 2; display: block; }
    
    .blog-card-placeholder {
        height: 220px; /* square */
    }
    /* Full-card clickable overlay */
    .card-link-overlay {
        position: absolute;
        inset: 0;
        z-index: 10;
    }
    
    .blog-card .card-body * {
        position: relative;
        z-index: 1;
    }
    
    /* Make "Czytaj" button visual only, not interactive */
    .blog-card .btn {
        pointer-events: none;
    }

    /* When image fails, show placeholder styling */
    .blog-card-image.no-image {
        height: 220px;
        background: #f1f3f5;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        line-height: 1.4;
        color: #333;
    }

    /* Responsive */
    @media (max-width: 1199.98px) {
        .masonry-grid {
            column-count: 3;
        }
        
        .blog-card-image,
        .blog-card-placeholder {
            height: 200px;
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
        
        .blog-card-image,
        .blog-card-placeholder {
            height: 180px;
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
        
        .blog-card-image,
        .blog-card-placeholder {
            height: 160px;
        }
        
        .blog-sections .container {
            padding-bottom: 60px;
        }
    }
</style>

@endsection
