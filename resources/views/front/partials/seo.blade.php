@php
    // $seo can be:
    // - an array with keys: title, description, image, canonical, keywords
    // - a model that implements ->seo_meta (array) or fields like title/excerpt/featured_image/seo_keywords
    $meta = [];
    if (isset($seo) && is_array($seo)) {
        $meta = $seo;
    } elseif (isset($seo) && is_object($seo)) {
        // try model style
        $meta = $seo->seo_meta ?? [];
    }

    // fallback from model variables
    $title = $meta['title'] ?? ($blogPost->title ?? ($post->title ?? ($package->name ?? ($pageTitle ?? null))));
    $description = $meta['description'] ?? ($blogPost->excerpt ?? ($post->excerpt ?? ($package->excerpt ?? ($pageDescription ?? null))));
    $image = $meta['image'] ?? ($blogPost->featured_image ?? ($post->featured_image ?? ($package->featured_image ?? null)));
    $canonical = $meta['canonical'] ?? ($canonical ?? request()->getUri());
    $keywords = $meta['keywords'] ?? null;

    $defaultTitle = 'Biuro Podróży RAFA – wycieczki szkolne i wyjazdy firmowe';
    $defaultDescription = 'Biuro Podróży RAFA organizuje wycieczki szkolne, zielone szkoły i wyjazdy integracyjne dla firm. Zaufaj specjalistom w turystyce szkolnej i podróżach szytych na miarę.';
    $baseKeywords = collect([
        'biuro podróży rafa',
        'wycieczki szkolne',
        'turystyka szkolna',
        'wyjazdy firmowe',
        'wyjazdy integracyjne',
        'zielone szkoły',
        'bprafa.pl'
    ]);

    if (!$keywords) {
        if (isset($blogPost) && !empty($blogPost->seo_keywords)) {
            $keywords = $blogPost->seo_keywords;
        } elseif (isset($post) && !empty($post->seo_keywords)) {
            $keywords = $post->seo_keywords;
        } elseif (isset($package) && !empty($package->seo_keywords)) {
            $keywords = $package->seo_keywords;
        } elseif (isset($eventTemplate) && !empty($eventTemplate->seo_keywords)) {
            $keywords = $eventTemplate->seo_keywords;
        }
    }

    $keywordsCollection = $keywords
        ? collect(preg_split('/\s*,\s*/', (string) $keywords, -1, PREG_SPLIT_NO_EMPTY))
        : collect();
    $keywords = $keywordsCollection->merge($baseKeywords)->map(fn($kw) => mb_strtolower(trim($kw)))->unique()->implode(', ');

    // Normalize image to absolute URL if starts without http
    if ($image && !str_starts_with($image, 'http')) {
        $image = asset('storage/' . ltrim($image, '/'));
    }

    if (!$image) {
        $image = asset('uploads/logo.png');
    }

    $title = trim((string) ($title ?? '')) ?: $defaultTitle;
    $description = trim((string) ($description ?? '')) ?: $defaultDescription;
    $keywords = $keywords ?: $baseKeywords->implode(', ');
@endphp

@if($title)
    <title>{{ $title }}</title>
    <meta name="title" content="{{ $title }}">
@endif

@if($description)
    <meta name="description" content="{{ $description }}">
@endif

@if($keywords)
    <meta name="keywords" content="{{ $keywords }}">
@endif

@if($canonical)
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:url" content="{{ $canonical }}">
@endif

@if($image)
    <meta property="og:image" content="{{ $image }}">
    <meta name="twitter:image" content="{{ $image }}">
@endif

@if($title)
    <meta property="og:title" content="{{ $title }}">
    <meta name="twitter:title" content="{{ $title }}">
@endif

@if($description)
    <meta property="og:description" content="{{ $description }}">
    <meta name="twitter:description" content="{{ $description }}">
@endif

<meta property="og:locale" content="pl_PL">
<meta property="og:site_name" content="Biuro Podróży RAFA">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
