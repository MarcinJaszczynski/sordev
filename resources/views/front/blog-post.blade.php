@extends('front.layout.master')

@section('head')
    @include('front.partials.seo')
@endsection

@section('main_content')

<div class="page-top">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="breadcrumb-container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Start</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('blog.global') }}">Aktualności</a></li>
                        <li class="breadcrumb-item active">{{ $blogPost->title }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top Navigation Buttons -->
<div class="container pt_30">
    <div class="blog-page-arrows pb_10">
    @if($previousPost)
    <a href="{{ route('blog.post.global', $previousPost->slug) }}">
            <button class="direction-button"><div class="previous">
                <i class="fas fa-arrow-left"></i> poprzedni wpis
            </div></button></a>
        @else
            <button class="direction-button" disabled> <i class="fas fa-arrow-left"></i> poprzedni wpis</button>
        @endif
    @if($nextPost)
    <a href="{{ route('blog.post.global', $nextPost->slug) }}">
            <button class="direction-button"><div class="next">
                    następny wpis <i class="fas fa-arrow-right"></i>
                </div></button></a>
        @else
            <button class="direction-button" disabled>następny wpis <i class="fas fa-arrow-right"></i></button>
        @endif
    </div>
</div>

<div class="container pt_20">
    <div class="row">
        <div>
            <article class="blog-post-single">
                <!-- Featured Image -->
                @if($blogPost->featured_image)
                    <div class="blog-post-featured-image mb-4">
                        <img src="{{ asset('storage/' . $blogPost->featured_image) }}" 
                             alt="{{ $blogPost->title }}" 
                             class="img-fluid rounded">
                    </div>
                @endif

                <!-- Title -->
                <h1 class="blog-post-title mb-3">{{ $blogPost->title }}</h1>

                <!-- Meta Information -->
                <div class="blog-post-meta mb-4">
                    <small class="text-muted">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Opublikowano: {{ $blogPost->published_at ? $blogPost->published_at->format('d.m.Y') : $blogPost->created_at->format('d.m.Y') }}
                    </small>
                </div>

                <!-- Excerpt (if exists) -->
                @if($blogPost->excerpt)
                    <div class="blog-post-excerpt mb-4">
                        <p class="lead text-muted">{{ $blogPost->excerpt }}</p>
                    </div>
                @endif

                <!-- Content -->
                <div class="blog-post-content">
                    {!! $blogPost->content !!}
                </div>

                <!-- Back to Blog Button -->
                <div class="blog-post-navigation mt-5 pt-4 border-top text-center">
                    <a href="{{ route('blog.global') }}" class="btn btn-primary">
                        <i class="fas fa-list me-2"></i>
                        Powrót do wszystkich postów
                    </a>
                </div>
            </article>
        </div>
    </div>
</div>

<div class="pb_70"></div>

<style>
    .blog-page-arrows {
        width: 100%;
        display: flex;
        justify-content: space-between;
    }

    .blog-page-arrows .direction-button{
        background: transparent;
        border: none !important;
        flex-direction: row;
        color: #333;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .blog-page-arrows .direction-button:hover {
        color: #000;
    }

    .blog-page-arrows .direction-button:disabled {
        color: #999;
        cursor: not-allowed;
    }

    .blog-page-arrows a {
        text-decoration: none;
    }

    .blog-post-single {
        padding: 0 15px;
    }
    
    .blog-post-featured-image img {
        width: 100%;
        height: auto;
        max-height: 400px;
        object-fit: cover;
    }
    
    .blog-post-title {
        color: #333;
        font-weight: 700;
        line-height: 1.3;
    }
    
    .blog-post-meta {
        border-bottom: 1px solid #e9ecef;
        padding-bottom: 1rem;
    }
    
    .blog-post-excerpt {
        font-size: 1.1rem;
        line-height: 1.6;
    }
    
    .blog-post-content {
        font-size: 1rem;
        line-height: 1.7;
        color: #333;
    }
    
    .blog-post-content h2,
    .blog-post-content h3,
    .blog-post-content h4 {
        color: #333;
        margin-top: 2rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }
    
    .blog-post-content p {
        margin-bottom: 1.5rem;
    }
    
    .blog-post-content img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        margin: 1.5rem 0;
    }
    
    .blog-post-content ul,
    .blog-post-content ol {
        margin-bottom: 1.5rem;
        padding-left: 2rem;
    }
    
    .blog-post-content li {
        margin-bottom: 0.5rem;
    }
    
    .blog-post-content blockquote {
        border-left: 4px solid #ce0d0d;
        padding-left: 1.5rem;
        margin: 2rem 0;
        font-style: italic;
        color: #666;
    }
    
    .blog-post-navigation {
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 2px solid #e9ecef;
    }

    .blog-post-navigation .btn {
        min-width: 120px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .blog-post-navigation .btn-primary {
        background-color: #ce0d0d;
        border-color: #ce0d0d;
    }

    .blog-post-navigation .btn-primary:hover {
        background-color: #af0b0b;
        border-color: #af0b0b;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(206, 13, 13, 0.3);
    }
    
    /* Mobile responsiveness */
    @media (max-width: 767.98px) {
        .blog-post-single {
            padding: 0 10px;
        }
        
        .blog-post-title {
            font-size: 1.75rem;
        }
        
        .blog-post-featured-image img {
            max-height: 250px;
        }
        
        .blog-post-excerpt {
            font-size: 1rem;
        }
        
        .blog-post-content {
            font-size: 0.95rem;
        }
        
        .container.pt_30 {
            padding-top: 20px;
        }
        
        .blog-page-arrows {
            flex-direction: row;
            gap: 20px;
        }
        
        .blog-page-arrows .direction-button {
            width: auto;
            text-align: center;
        }
        
        .blog-post-navigation .btn {
            width: 100%;
            min-width: auto;
        }
    }
    
    @media (min-width: 768px) and (max-width: 991.98px) {
        .blog-post-featured-image img {
            max-height: 350px;
        }
    }
    
    @media (min-width: 992px) {
        .blog-post-title {
            font-size: 2.5rem;
        }
    }
</style>

@endsection
